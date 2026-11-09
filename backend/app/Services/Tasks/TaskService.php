<?php

namespace App\Services\Tasks;

use App\Enums\ColumnCategory;
use App\Enums\TaskEvent;
use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskColumn;
use App\Models\TaskHistory;
use App\Models\TaskLabel;
use App\Models\User;
use App\Services\Support\NumberSequence;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Task lifecycle. Every change that touches positions or the column runs under the board row lock, so two
 * people dragging cards at the same time never produce duplicate or missing positions.
 */
class TaskService
{
    public function __construct(private readonly BoardService $boards, private readonly NumberSequence $numbers) {}

    /**
     * @param  array<string, mixed>  $data  title, description, column_id, organization_id, project, assignee_id, priority,
     *                                      start_date, due_date, label_ids, watcher_ids, ticket_id, recurrence_id, occurrence_date
     */
    public function create(TaskBoard $board, array $data, User $by): Task
    {
        return DB::transaction(function () use ($board, $data, $by): Task {
            $board = $this->boards->lock($board);
            $this->boards->writable($board);
            $column = $this->columnOf($board, $data['column_id'] ?? null);
            $organizationId = $this->organizationFor($board, $data['organization_id'] ?? null);
            $assigneeId = $this->eligible($board, $data['assignee_id'] ?? null, 'assignee_id');
            $year = CarbonImmutable::now((string) config('app.display_timezone'))->year;
            $task = Task::query()->create([
                'number' => sprintf('T-%d-%05d', $year, $this->numbers->next("task:{$year}")),
                'board_id' => $board->id, 'column_id' => $column->id,
                'position' => (int) Task::query()->where('column_id', $column->id)->lockForUpdate()->max('position') + 1,
                'organization_id' => $organizationId, 'title' => trim((string) $data['title']), 'description' => $data['description'] ?? null,
                'project' => $data['project'] ?? null, 'assignee_id' => $assigneeId,
                'priority' => $data['priority'] ?? TaskPriority::Normal, 'start_date' => $data['start_date'] ?? null, 'due_date' => $data['due_date'] ?? null,
                'completed_at' => $column->category->finishes() ? CarbonImmutable::now() : null,
                'ticket_id' => $data['ticket_id'] ?? null, 'recurrence_id' => $data['recurrence_id'] ?? null,
                'occurrence_date' => $data['occurrence_date'] ?? null,
            ]);
            $task->labels()->sync($this->labelIds($board, $data['label_ids'] ?? []));
            $task->watchers()->sync($this->eligibleMany($board, $data['watcher_ids'] ?? []));
            $this->record($task, TaskEvent::Created, $by, ['column' => $column->name]);

            return $task;
        });
    }

    /** @param  array<string, mixed>  $payload */
    public function record(Task $task, TaskEvent $event, ?User $by, array $payload = []): void
    {
        TaskHistory::query()->create(['task_id' => $task->id, 'event' => $event, 'payload' => $payload === [] ? null : $payload, 'user_id' => $by?->id]);
    }

    /** The column must be on the board; default: the first open column. */
    public function columnOf(TaskBoard $board, mixed $columnId): TaskColumn
    {
        $column = $columnId === null
            ? TaskColumn::query()->where('board_id', $board->id)->where('category', ColumnCategory::Open->value)->orderBy('position')->first()
            : TaskColumn::query()->where('board_id', $board->id)->find((int) $columnId);
        if ($column === null) {
            throw ValidationException::withMessages(['column_id' => __('messages.task_column_invalid')]);
        }

        return $column;
    }

    /** The board's organization or one of its descendants. */
    public function organizationFor(TaskBoard $board, mixed $organizationId): int
    {
        $id = $organizationId === null ? $board->organization_id : (int) $organizationId;
        if (! in_array($id, OrganizationSubtree::ids($board->organization_id), true)) {
            throw ValidationException::withMessages(['organization_id' => __('messages.task_organization_invalid')]);
        }

        return $id;
    }

    /** An active user who can open the board (tasks.view + access to its organization). */
    public function eligible(TaskBoard $board, mixed $userId, string $field): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        $user = User::query()->withoutGlobalScopes()->where('is_active', true)->find((int) $userId);
        if ($user === null || ! $user->can('tasks.view') || ! $user->canAccessOrganization($board->organization_id)) {
            throw ValidationException::withMessages([$field => __('messages.task_user_cannot_see_board')]);
        }

        return $user->id;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    public function eligibleMany(TaskBoard $board, array $ids): array
    {
        return array_values(array_unique(array_map(fn ($id) => (int) $this->eligible($board, $id, 'watcher_ids'), array_filter($ids, fn ($id) => $id !== null))));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    public function labelIds(TaskBoard $board, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $valid = TaskLabel::query()->where('board_id', $board->id)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages(['label_ids' => __('messages.task_label_invalid')]);
        }

        return $valid;
    }
}
