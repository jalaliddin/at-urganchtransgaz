<?php

namespace Tests\Feature\Itsm;

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\ProblemHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Itsm\ProblemNotification;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ProblemTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $it;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Tashkent'));
        $this->center = Organization::factory()->create(['name' => 'Urganchtransgaz']);
        $this->branch = Organization::factory()->create(['name' => 'Xiva GTS', 'parent_id' => $this->center->id]);
        $this->head = $this->makeStaff(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->it = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->requester = $this->makeStaff(Roles::EMPLOYEE, $this->branch);
    }

    /** @param  array<string, mixed>  $attributes */
    private function open(array $attributes = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->it)->postJson('/api/v1/problems', [
            'organization_id' => $this->branch->id, 'title' => 'Printerlar har hafta qog\'oz tiqadi', 'priority' => 'high',
            'category_id' => $this->category('printer')->id, ...$attributes,
        ]);
    }

    private function move(int $id, array $payload): TestResponse
    {
        return $this->actingAs($this->it)->postJson("/api/v1/problems/{$id}/transition", $payload);
    }

    public function test_the_lifecycle_needs_a_workaround_then_a_root_cause_and_is_recorded(): void
    {
        $id = $this->open(['assignee_id' => $this->it->id])->assertCreated()->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.assignee.id', $this->it->id)->json('data.id');
        $this->assertMatchesRegularExpression('/^PRB-2026-\d{5}$/', $this->getJson("/api/v1/problems/{$id}")->json('data.number'));

        $this->move($id, ['status' => 'known_error'])->assertStatus(422)->assertJsonValidationErrors('workaround');
        $this->move($id, ['status' => 'known_error', 'workaround' => 'Qog\'ozni quruq joyda saqlash'])->assertOk()->assertJsonPath('data.status', 'known_error');
        $this->move($id, ['status' => 'resolved'])->assertStatus(422)->assertJsonValidationErrors(['root_cause', 'resolution']);
        $this->move($id, ['status' => 'resolved', 'root_cause' => 'Nam qog\'oz', 'resolution' => 'Ombor quruq xonaga ko\'chirildi'])
            ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.root_cause', 'Nam qog\'oz');
        $this->assertNotNull($this->getJson("/api/v1/problems/{$id}")->json('data.resolved_at'));
        $this->move($id, ['status' => 'investigating'])->assertOk()->assertJsonPath('data.resolved_at', null);
        $this->move($id, ['status' => 'closed'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->move($id, ['status' => 'closed', 'reason' => 'Boshqa muammoning takrori'])->assertOk()->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.close_reason', 'Boshqa muammoning takrori');
        $this->move($id, ['status' => 'investigating'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->actingAs($this->it)->putJson("/api/v1/problems/{$id}", ['title' => 'X', 'priority' => 'low'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->postJson("/api/v1/problems/{$id}/comments", ['comment' => 'Yopildi'])->assertCreated();

        $this->assertSame(['created', 'status_changed', 'status_changed', 'status_changed', 'status_changed', 'commented'],
            ProblemHistory::query()->where('problem_id', $id)->orderBy('id')->pluck('event')->map(fn ($e) => $e->value)->all());

        $orgHead = $this->makeUser(Roles::ORG_HEAD, $this->branch);
        $this->actingAs($orgHead)->getJson("/api/v1/problems/{$id}")->assertOk()->assertJsonPath('data.can.manage', false);
        $this->open([], $orgHead)->assertForbidden();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()))->getJson("/api/v1/problems/{$id}")->assertNotFound();
        $this->open(['organization_id' => $this->center->id])->assertStatus(422)->assertJsonValidationErrors('organization_id');
    }

    public function test_tickets_link_only_inside_the_problem_subtree_and_the_ticket_lists_its_problems(): void
    {
        $id = $this->open()->json('data.id');
        $mine = $this->ticketBy($this->requester);
        $central = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->center));
        $foreign = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, Organization::factory()->create()));

        $this->actingAs($this->head)->postJson("/api/v1/problems/{$id}/tickets", ['ticket_ids' => [$central->id]])
            ->assertStatus(422)->assertJsonValidationErrors('ticket_ids');
        $this->actingAs($this->it)->postJson("/api/v1/problems/{$id}/tickets", ['ticket_ids' => [$foreign->id]])->assertStatus(422);
        $this->postJson("/api/v1/problems/{$id}/tickets", ['ticket_ids' => [$mine->id]])->assertOk()
            ->assertJsonCount(1, 'data.tickets')->assertJsonPath('data.tickets.0.number', $mine->number);
        $this->postJson("/api/v1/problems/{$id}/tickets", ['ticket_ids' => [$mine->id]])->assertOk()->assertJsonCount(1, 'data.tickets');

        $this->getJson("/api/v1/tickets/{$mine->id}/problems")->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($this->requester)->getJson("/api/v1/tickets/{$mine->id}/problems")->assertForbidden();
        $this->actingAs($this->it)->deleteJson("/api/v1/problems/{$id}/tickets/{$mine->id}")->assertOk()->assertJsonCount(0, 'data.tickets');
        $this->assertSame(['created', 'tickets_linked', 'ticket_unlinked'],
            ProblemHistory::query()->where('problem_id', $id)->orderBy('id')->pluck('event')->map(fn ($e) => $e->value)->all());
    }

    public function test_the_assignee_must_work_on_problems_and_a_resolution_reaches_open_ticket_assignees(): void
    {
        Notification::fake();
        $staff = $this->makeStaff(Roles::CENTRAL_IT_STAFF, $this->center, true);
        $this->open(['assignee_id' => $this->makeUser(Roles::ORG_HEAD, $this->branch)->id])->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $id = $this->open(['assignee_id' => $staff->id])->assertCreated()->json('data.id');
        Notification::assertSentTo($staff, ProblemNotification::class, fn ($n) => $n->event === 'assigned');

        $open = $this->ticketBy($this->requester);
        $done = $this->ticketBy($this->requester);
        Ticket::query()->whereKey($open->id)->update(['assignee_id' => $staff->id]);
        $other = $this->makeStaff(Roles::CENTRAL_IT_STAFF, $this->center, true);
        Ticket::query()->whereKey($done->id)->update(['assignee_id' => $other->id, 'status' => TicketStatus::Closed->value]);
        $this->actingAs($this->it)->postJson("/api/v1/problems/{$id}/tickets", ['ticket_ids' => [$open->id, $done->id]])->assertOk();

        $this->move($id, ['status' => 'resolved', 'root_cause' => 'Drayver', 'resolution' => 'Drayver yangilandi'])->assertOk();
        Notification::assertSentTo($staff, ProblemNotification::class, fn ($n) => $n->event === 'resolved');
        Notification::assertNotSentTo($other, ProblemNotification::class);
    }

    public function test_recurring_tickets_become_candidates_until_an_open_problem_covers_them(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Asia/Tashkent'));
        $this->ticketBy($this->requester);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Tashkent'));
        $printer = collect(range(1, 3))->map(fn () => $this->ticketBy($this->requester)->id)->all();
        collect(range(1, 2))->each(fn () => $this->ticketBy($this->requester, ['category_id' => $this->category('network')->id]));
        $stranger = $this->makeStaff(Roles::EMPLOYEE, Organization::factory()->create());
        collect(range(1, 3))->each(fn () => $this->ticketBy($stranger));

        $candidates = $this->actingAs($this->it)->getJson('/api/v1/problems/candidates')->assertOk()->json('data');
        $this->assertCount(1, $candidates);
        $this->assertSame(['printer', $this->branch->id, 3], [$candidates[0]['category']['code'], $candidates[0]['organization']['id'], $candidates[0]['count']]);
        $this->assertEqualsCanonicalizing($printer, $candidates[0]['ticket_ids']);

        $this->open(['ticket_ids' => $printer])->assertCreated()->assertJsonCount(3, 'data.tickets');
        $this->getJson('/api/v1/problems/candidates')->assertOk()->assertJsonCount(0, 'data');
        app(SettingsService::class)->update(['problems.recurrence_threshold' => 2]);
        $this->getJson('/api/v1/problems/candidates')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.category.code', 'network');
    }
}
