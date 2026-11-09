<?php

namespace Tests\Feature\Tasks;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTasks;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\Feature\Files\SafeUploadTest;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use CreatesTasks, CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private Organization $branch;

    private User $it;

    private User $staff;

    private TaskBoard $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00', 'Asia/Tashkent'));
        $this->org = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->org->id]);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org, true);
        $this->staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->org, true);
        $this->board = $this->boardIn($this->org);
    }

    /** @return list<string> titles of a column in position order */
    private function titles(string $column): array
    {
        return Task::query()->where('column_id', $this->column($this->board, $column)->id)->orderBy('position')->pluck('title')->all();
    }

    public function test_a_task_is_numbered_placed_at_the_end_and_checked(): void
    {
        $label = $this->actingAs($this->it)->postJson("/api/v1/task-boards/{$this->board->id}/labels", ['name' => 'Tarmoq'])->json('data.id');
        $url = "/api/v1/task-boards/{$this->board->id}/tasks";
        $first = $this->postJson($url, ['title' => 'Switchni almashtirish', 'organization_id' => $this->branch->id, 'priority' => 'high',
            'assignee_id' => $this->staff->id, 'due_date' => '2026-11-12', 'label_ids' => [$label], 'watcher_ids' => [$this->it->id], 'project' => 'Tarmoq'])
            ->assertCreated()->assertJsonPath('data.column.name', 'Backlog')->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.assignee.id', $this->staff->id)->assertJsonPath('data.labels.0.name', 'Tarmoq')
            ->assertJsonPath('data.watchers.0.id', $this->it->id)->assertJsonPath('data.history.0.event', 'created')->json('data');
        $this->assertMatchesRegularExpression('/^T-2026-\d{5}$/', $first['number']);
        $this->postJson($url, ['title' => 'Ikkinchi'])->assertCreated()->assertJsonPath('data.position', 2);

        $this->postJson($url, ['title' => 'X', 'organization_id' => Organization::factory()->create()->id])->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->postJson($url, ['title' => 'X', 'assignee_id' => $this->makeUser(Roles::EMPLOYEE, $this->org)->id])->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        // A read-only viewer may watch a task but cannot be responsible for it.
        $viewer = $this->makeUser(Roles::ORG_HEAD, $this->org);
        $this->postJson($url, ['title' => 'X', 'assignee_id' => $viewer->id])->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->postJson($url, ['title' => 'Kuzatiladi', 'watcher_ids' => [$viewer->id]])->assertCreated();
        $this->postJson($url, ['title' => 'X', 'assignee_id' => $this->makeUser(Roles::CENTRAL_IT_STAFF, Organization::factory()->create())->id])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $other = $this->boardIn($this->org, 'Boshqa');
        $foreignLabel = $this->postJson("/api/v1/task-boards/{$other->id}/labels", ['name' => 'Y'])->json('data.id');
        $this->postJson($url, ['title' => 'X', 'label_ids' => [$foreignLabel]])->assertStatus(422)->assertJsonValidationErrors('label_ids');
        $this->postJson($url, ['title' => 'X', 'column_id' => $this->column($other, 'Backlog')->id])->assertStatus(422)->assertJsonValidationErrors('column_id');

        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org))->postJson($url, ['title' => 'X'])->assertForbidden();
        $foreignBoard = $this->boardIn(Organization::factory()->create());
        $this->actingAs($this->it)->postJson("/api/v1/task-boards/{$foreignBoard->id}/tasks", ['title' => 'X'])->assertNotFound();
        $this->getJson('/api/v1/tasks/'.$this->taskOn($foreignBoard, $this->it)->id)->assertNotFound();
    }

    public function test_moving_renumbers_both_columns_and_finishing_sets_the_completed_date(): void
    {
        [$a, $b, $c] = array_map(fn (string $t) => $this->taskOn($this->board, $this->it, ['title' => $t, 'due_date' => '2026-11-01']), ['A', 'B', 'C']);
        $this->actingAs($this->it);
        $move = fn (Task $t, string $column, int $position) => $this->postJson("/api/v1/tasks/{$t->id}/move", ['column_id' => $this->column($this->board, $column)->id, 'position' => $position]);

        $move($c, 'Backlog', 1)->assertOk();
        $this->assertSame(['C', 'A', 'B'], $this->titles('Backlog'));
        $move($a, 'Jarayonda', 5)->assertOk()->assertJsonPath('data.position', 1)->assertJsonPath('data.is_overdue', true);
        $this->assertSame(['C', 'B'], $this->titles('Backlog'));
        $this->assertSame([1, 2], Task::query()->where('column_id', $this->column($this->board, 'Backlog')->id)->orderBy('position')->pluck('position')->all());

        $move($a, 'Bajarildi', 1)->assertOk()->assertJsonPath('data.is_overdue', false)->assertJsonPath('data.history.0.event', 'completed');
        $this->assertNotNull($a->refresh()->completed_at);
        $move($a, 'Tekshiruvda', 1)->assertOk()->assertJsonPath('data.completed_at', null)->assertJsonPath('data.history.0.event', 'reopened');

        $this->postJson("/api/v1/tasks/{$b->id}/move", ['column_id' => $this->column($this->boardIn($this->org, 'Z'), 'Backlog')->id, 'position' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('column_id');
    }

    public function test_only_the_responsible_person_the_creator_or_a_manager_change_a_task(): void
    {
        $task = $this->taskOn($this->board, $this->it, ['assignee_id' => $this->staff->id]);
        $other = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->org, true);
        $head = $this->makeUser(Roles::ORG_HEAD, $this->org);
        $to = ['column_id' => $this->column($this->board, 'Jarayonda')->id, 'position' => 1];

        $this->actingAs($other)->getJson("/api/v1/tasks/{$task->id}")->assertOk()->assertJsonPath('data.can.edit', false)->assertJsonPath('data.can.comment', true);
        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'X'])->assertForbidden();
        $this->postJson("/api/v1/tasks/{$task->id}/move", $to)->assertForbidden();
        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Men ham yordam beraman'])->assertCreated();
        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();

        $this->actingAs($head)->getJson("/api/v1/tasks/{$task->id}")->assertOk()->assertJsonPath('data.can.comment', false);
        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'X'])->assertForbidden();

        $this->actingAs($this->staff)->postJson("/api/v1/tasks/{$task->id}/move", $to)->assertOk();
        $this->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Yangi nom', 'priority' => 'urgent', 'assignee_id' => $other->id])->assertOk()
            ->assertJsonPath('data.history.0.event', 'assigned');
        $this->assertSame('updated', $task->histories()->latest('id')->skip(1)->value('event')->value);

        $this->board->update(['is_archived' => true]);
        $this->actingAs($this->it)->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Z'])->assertStatus(409);
    }

    public function test_checklist_comments_files_and_the_board_view(): void
    {
        Storage::fake('local');
        $task = $this->taskOn($this->board, $this->it, ['assignee_id' => $this->it->id, 'due_date' => '2026-11-01']);
        $this->taskOn($this->board, $this->staff, ['title' => 'Boshqaniki']);
        $old = $this->taskOn($this->board, $this->it, ['title' => 'Eski ish', 'column_id' => $this->column($this->board, 'Bajarildi')->id]);
        Task::query()->whereKey($old->id)->update(['completed_at' => '2026-09-01 10:00:00']);
        $this->actingAs($this->it);

        $item = $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['title' => 'Kabelni tekshirish'])->assertCreated()->json('data.checklist.0.id');
        $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['title' => 'Portni sozlash'])->assertCreated();
        $this->patchJson("/api/v1/task-checklist-items/{$item}", ['is_done' => true])->assertOk()->assertJsonPath('data.checklist.0.is_done', true);
        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Switch keldi'])->assertCreated()->assertJsonPath('data.comments.0.body', 'Switch keldi');
        $this->post("/api/v1/tasks/{$task->id}/attachments", ['files' => [UploadedFile::fake()->createWithContent('sxema.pdf', SafeUploadTest::pdf())]],
            ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.attachments.0.file.name', 'sxema.pdf');
        $events = collect($this->getJson("/api/v1/tasks/{$task->id}")->json('data.history'))->pluck('event')->all();
        $this->assertEqualsCanonicalizing(['created', 'checklist_changed', 'checklist_changed', 'checklist_changed', 'commented', 'file_added'], $events);

        $columns = collect($this->getJson("/api/v1/task-boards/{$this->board->id}/cards")->assertOk()->json('data.columns'));
        $backlog = $columns->firstWhere('name', 'Backlog');
        $this->assertCount(2, $backlog['cards']);
        $card = collect($backlog['cards'])->firstWhere('id', $task->id);
        $this->assertSame([2, 1, 1, true], [$card['checklist_total'], $card['checklist_done'], $card['comments_count'], $card['is_overdue']]);
        $this->assertTrue($card['can_move']);
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_STAFF, $this->org, true));
        $this->assertFalse(collect($this->getJson("/api/v1/task-boards/{$this->board->id}/cards")->json('data.columns.0.cards'))->firstWhere('id', $task->id)['can_move']);
        $this->actingAs($this->it);
        $this->assertSame(0, count($columns->firstWhere('name', 'Bajarildi')['cards']));
        $this->assertSame(1, $columns->firstWhere('name', 'Bajarildi')['hidden_done']);
        $this->assertCount(1, $this->getJson("/api/v1/task-boards/{$this->board->id}/cards?filter[mine]=1")->json('data.columns.0.cards'));
        $this->assertCount(1, $this->getJson("/api/v1/task-boards/{$this->board->id}/cards?filter[overdue]=1")->json('data.columns.0.cards'));
        $this->getJson('/api/v1/tasks?filter[mine]=1&filter[open]=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_ticket_becomes_a_linked_task(): void
    {
        $requester = $this->makeStaff(Roles::EMPLOYEE, $this->branch);
        $ticket = $this->ticketBy($requester, ['title' => 'Printer ishlamayapti', 'description' => '3-qavat']);
        $this->actingAs($this->it);

        $task = $this->postJson("/api/v1/tickets/{$ticket->id}/tasks", ['board_id' => $this->board->id, 'assignee_id' => $this->staff->id])->assertCreated()
            ->assertJsonPath('data.title', 'Printer ishlamayapti')->assertJsonPath('data.ticket.number', $ticket->number)
            ->assertJsonPath('data.organization.id', $this->branch->id)->json('data.id');
        $this->getJson("/api/v1/tickets/{$ticket->id}/tasks")->assertOk()->assertJsonPath('data.0.id', $task);

        $foreignTicket = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, Organization::factory()->create()));
        $this->postJson("/api/v1/tickets/{$foreignTicket->id}/tasks", ['board_id' => $this->board->id])->assertNotFound();
    }

    public function test_a_task_stays_editable_when_its_people_lost_access(): void
    {
        $watcher = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->org, true);
        $task = $this->taskOn($this->board, $this->it, ['assignee_id' => $this->staff->id, 'watcher_ids' => [$watcher->id]]);
        $this->staff->update(['is_active' => false]);
        $watcher->update(['is_active' => false]);

        $this->actingAs($this->it)->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Yangi nom', 'assignee_id' => $this->staff->id, 'watcher_ids' => [$watcher->id]])
            ->assertOk()->assertJsonPath('data.title', 'Yangi nom');
        $newcomer = $this->makeUser(Roles::EMPLOYEE, $this->org);
        $this->putJson("/api/v1/tasks/{$task->id}", ['watcher_ids' => [$watcher->id, $newcomer->id]])->assertStatus(422)->assertJsonValidationErrors('watcher_ids');
    }
}
