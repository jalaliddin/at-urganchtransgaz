<?php

namespace Tests\Feature\Tasks;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;
use App\Services\Exports\TaskReport;
use App\Services\Exports\TaskWorkloadReport;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesTasks;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TaskReportsTest extends TestCase
{
    use CreatesTasks, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $aziz;

    private User $dilnoza;

    private TaskBoard $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'Asia/Tashkent'));
        $this->center = Organization::factory()->create(['name' => 'Markaz']);
        $this->branch = Organization::factory()->create(['name' => 'Xiva GTS', 'parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->aziz = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->center, true);
        $this->dilnoza = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->center, true);
        $this->board = $this->boardIn($this->center);

        $done = $this->column($this->board, 'Bajarildi')->id;
        $this->taskOn($this->board, $this->head, ['title' => 'A1', 'assignee_id' => $this->aziz->id, 'due_date' => '2026-11-05', 'organization_id' => $this->branch->id]);
        $this->taskOn($this->board, $this->head, ['title' => 'A2', 'assignee_id' => $this->aziz->id, 'due_date' => '2026-11-14']);
        $this->taskOn($this->board, $this->head, ['title' => 'A3', 'assignee_id' => $this->aziz->id, 'column_id' => $done]);
        $this->taskOn($this->board, $this->head, ['title' => 'D1', 'assignee_id' => $this->dilnoza->id, 'due_date' => '2026-12-20', 'organization_id' => $this->branch->id]);
        $this->taskOn($this->board, $this->head, ['title' => 'U1']);
        $foreign = $this->boardIn(Organization::factory()->create());
        $this->taskOn($foreign, $this->head, ['title' => 'Begona']);
    }

    public function test_workload_per_person_and_organization(): void
    {
        $this->actingAs($this->head);
        $data = $this->getJson('/api/v1/tasks/workload')->assertOk()->json('data');
        $aziz = collect($data['by_assignee'])->firstWhere('user.id', $this->aziz->id);
        $this->assertSame(['open' => 2, 'overdue' => 1, 'due_week' => 1, 'done_30' => 1], array_intersect_key($aziz, array_flip(['open', 'overdue', 'due_week', 'done_30'])));
        $unassigned = collect($data['by_assignee'])->firstWhere('user', null);
        $this->assertSame(1, $unassigned['open']);
        $branch = collect($data['by_organization'])->firstWhere('organization.id', $this->branch->id);
        $this->assertSame([2, 1], [$branch['open'], $branch['overdue']]);
        $this->assertSame(5, collect($data['by_organization'])->sum('open') + collect($data['by_organization'])->sum('done_30'));

        $this->getJson("/api/v1/tasks/workload?filter[organization]={$this->branch->id}")->assertOk()->assertJsonCount(2, 'data.by_assignee');
        $this->actingAs($this->aziz)->getJson('/api/v1/tasks/workload')->assertForbidden();
    }

    public function test_dashboard_widget_and_exports(): void
    {
        $this->actingAs($this->aziz)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.tasks.my_open', 2)->assertJsonPath('data.tasks.my_overdue', 1)->assertJsonPath('data.tasks.overdue', 1);
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->center))->getJson('/api/v1/dashboard')->assertOk()->assertJsonMissingPath('data.tasks');

        Auth::forgetGuards();
        $rows = iterator_to_array(app(TaskReport::class)->rows($this->aziz, [], 'uz'), false);
        $this->assertCount(5, $rows);
        $this->assertNotContains('Begona', array_column($rows, 1));
        $open = iterator_to_array(app(TaskReport::class)->rows($this->aziz, ['open' => '1', 'assignee' => (string) $this->aziz->id], 'uz'), false);
        $this->assertSame(['A1', 'A2'], array_column($open, 1));
        $workload = iterator_to_array(app(TaskWorkloadReport::class)->rows($this->head, [], 'uz'), false);
        $this->assertContains($this->aziz->name, array_column($workload, 0));
        $this->assertSame(5, Task::query()->where('board_id', $this->board->id)->count());
    }
}
