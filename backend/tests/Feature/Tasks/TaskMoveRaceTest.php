<?php

namespace Tests\Feature\Tasks;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskColumn;
use App\Models\User;
use App\Services\Tasks\BoardService;
use App\Services\Tasks\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two drags in the same column on separate MySQL connections: B has read inside its transaction before A's
 * move commits. Positions in the column must stay 1..n without duplicates.
 */
class TaskMoveRaceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['aux', 'aux_a', 'aux_b'] as $name) {
            config(["database.connections.{$name}" => config('database.connections.'.config('database.default'))]);
        }
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection(config('database.default'));
        $aux = DB::connection('aux');
        if (isset($this->ids['board'])) {
            $tasks = $aux->table('tasks')->where('board_id', $this->ids['board'])->pluck('id');
            $aux->table('task_histories')->whereIn('task_id', $tasks)->delete();
            $aux->table('tasks')->whereIn('id', $tasks)->delete();
            $aux->table('task_columns')->where('board_id', $this->ids['board'])->delete();
            $audit = $aux->table('audit_logs')->where('auditable_type', 'task_board')->where('auditable_id', $this->ids['board'])->pluck('id');
            $aux->table('audit_logs')->whereIn('id', $audit)->delete();
            $aux->table('task_boards')->where('id', $this->ids['board'])->delete();
            $aux->table('users')->where('id', $this->ids['user'])->delete();
            $aux->table('organizations')->where('id', $this->ids['organization'])->delete();
            $aux->table('number_sequences')->where('key', 'like', 'task:%')->delete();
        }
        foreach (['aux_b', 'aux_a', 'aux'] as $name) {
            DB::connection($name)->disconnect();
        }
        parent::tearDown();
    }

    public function test_a_move_committed_after_the_snapshot_keeps_positions_unique(): void
    {
        [$board, $column, $user, $tasks] = $this->committedFixture();
        $service = app(TaskService::class);

        $b = DB::connection('aux_b');
        $b->beginTransaction();
        $b->table('tasks')->where('column_id', $column->id)->count();

        DB::setDefaultConnection('aux_a');
        $service->move(Task::on('aux')->findOrFail($tasks[2]), $column->id, 1, $user);

        DB::setDefaultConnection('aux_b');
        $service->move(Task::on('aux')->findOrFail($tasks[3]), $column->id, 1, $user);
        $b->commit();

        $positions = DB::connection('aux')->table('tasks')->where('column_id', $column->id)->orderBy('position')->pluck('position', 'id')->all();
        $this->assertSame([1, 2, 3, 4], array_values($positions));
        $this->assertSame([$tasks[3], $tasks[2], $tasks[0], $tasks[1]], array_keys($positions));
    }

    /** @return array{TaskBoard, TaskColumn, User, list<int>} */
    private function committedFixture(): array
    {
        $organization = Organization::factory()->connection('aux')->create();
        $user = User::factory()->connection('aux')->create(['organization_id' => $organization->id]);
        DB::setDefaultConnection('aux');
        $board = app(BoardService::class)->create(['organization_id' => $organization->id, 'name' => 'Race'], $user);
        $column = TaskColumn::query()->where('board_id', $board->id)->orderBy('position')->firstOrFail();
        $tasks = [];
        foreach (['A', 'B', 'C', 'D'] as $title) {
            $tasks[] = app(TaskService::class)->create($board, ['title' => $title], $user)->id;
        }
        DB::setDefaultConnection(config('database.default'));
        $this->ids = ['organization' => $organization->id, 'user' => $user->id, 'board' => $board->id];

        return [$board, $column, $user, $tasks];
    }
}
