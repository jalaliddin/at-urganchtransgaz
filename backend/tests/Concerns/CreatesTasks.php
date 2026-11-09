<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskColumn;
use App\Models\User;
use App\Services\Tasks\BoardService;
use App\Services\Tasks\TaskService;

/** Board and task fixtures. Requires InteractsWithAccess. */
trait CreatesTasks
{
    protected function boardIn(Organization $organization, string $name = 'AT xizmati'): TaskBoard
    {
        return app(BoardService::class)->create(['organization_id' => $organization->id, 'name' => $name], User::factory()->create());
    }

    protected function column(TaskBoard $board, string $name): TaskColumn
    {
        return TaskColumn::query()->where('board_id', $board->id)->where('name', $name)->firstOrFail();
    }

    /** @param  array<string, mixed>  $attributes */
    protected function taskOn(TaskBoard $board, User $by, array $attributes = []): Task
    {
        return app(TaskService::class)->create($board, ['title' => 'Printerni sozlash', ...$attributes], $by);
    }
}
