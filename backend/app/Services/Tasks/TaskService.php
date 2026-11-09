<?php

namespace App\Services\Tasks;

use App\Enums\ColumnCategory;
use App\Enums\TaskEvent;
use App\Enums\TaskPriority;
use App\Models\StoredFile;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskBoard;
use App\Models\TaskChecklistItem;
use App\Models\TaskColumn;
use App\Models\TaskComment;
use App\Models\TaskHistory;
use App\Models\TaskLabel;
use App\Models\User;
use App\Notifications\Tasks\TaskNotification;
use App\Services\Files\FileStore;
use App\Services\Support\NumberSequence;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Task lifecycle. Every change that touches positions or the column runs under the board row lock, so two
 * people dragging cards at the same time never produce duplicate or missing positions.
 */
class TaskService
{
    /** Fields whose changes are listed in the history. */
    private const TRACKED = ['title', 'description', 'organization_id', 'project', 'priority', 'start_date', 'due_date'];

    public function __construct(
        private readonly BoardService $boards,
        private readonly NumberSequence $numbers,
        private readonly FileStore $files,
    ) {}

    /**
     * @param  array<string, mixed>  $data  title, description, column_id, organization_id, project, assignee_id, priority,
     *                                      start_date, due_date, label_ids, watcher_ids, ticket_id, recurrence_id, occurrence_date
     */
    public function create(TaskBoard $board, array $data, ?User $by): Task
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
            $this->notifyAssigned($task, $by);

            return $task;
        });
    }

    /** @param  array<string, mixed>  $data  title, description, organization_id, project, assignee_id, priority, start_date, due_date, label_ids, watcher_ids */
    public function update(Task $task, array $data, User $by): Task
    {
        return $this->locked($task, function (Task $t, TaskBoard $board) use ($data, $by): void {
            if (array_key_exists('organization_id', $data)) {
                $data['organization_id'] = $this->organizationFor($board, $data['organization_id']);
            }
            $t->fill(array_intersect_key($data, array_flip(self::TRACKED)));
            $changed = array_values(array_filter(self::TRACKED, fn (string $f) => $t->isDirty($f)));
            $t->save();
            if ($changed !== []) {
                $this->record($t, TaskEvent::Updated, $by, ['fields' => $changed]);
            }
            if (array_key_exists('label_ids', $data)) {
                $t->labels()->sync($this->labelIds($board, (array) $data['label_ids']));
            }
            if (array_key_exists('watcher_ids', $data)) {
                $before = $t->watchers()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                $after = $this->eligibleMany($board, (array) $data['watcher_ids']);
                sort($after);
                if ($before !== $after) {
                    $t->watchers()->sync($after);
                    $this->record($t, TaskEvent::WatchersChanged, $by);
                }
            }
            if (array_key_exists('assignee_id', $data)) {
                $assignee = $this->eligible($board, $data['assignee_id'], 'assignee_id');
                if ($assignee !== $t->assignee_id) {
                    $t->forceFill(['assignee_id' => $assignee])->save();
                    $this->record($t, TaskEvent::Assigned, $by, ['assignee' => $assignee === null ? null : User::query()->withoutGlobalScopes()->whereKey($assignee)->value('name')]);
                    $this->notifyAssigned($t, $by);
                }
            }
        });
    }

    /**
     * Drag-and-drop: into $columnId at 1-based $position (clamped). Both columns are renumbered 1..n under the
     * board lock with locking reads, so a move committed while this one waited is taken into account.
     */
    public function move(Task $task, int $columnId, int $position, User $by): Task
    {
        return $this->locked($task, function (Task $t, TaskBoard $board) use ($columnId, $position, $by): void {
            $target = $this->columnOf($board, $columnId);
            $from = TaskColumn::query()->findOrFail($t->column_id);
            $siblings = Task::query()->where('column_id', $target->id)->whereKeyNot($t->id)->orderBy('position')->orderBy('id')
                ->lockForUpdate()->pluck('id')->all();
            $index = max(0, min($position - 1, count($siblings)));
            array_splice($siblings, $index, 0, [$t->id]);
            $this->renumber($siblings);
            if ($from->id !== $target->id) {
                $this->renumber(Task::query()->where('column_id', $from->id)->whereKeyNot($t->id)->orderBy('position')->orderBy('id')
                    ->lockForUpdate()->pluck('id')->all());
                $t->forceFill(['column_id' => $target->id])->save();
                $this->record($t, TaskEvent::Moved, $by, ['from' => $from->name, 'to' => $target->name]);
                if (! $from->category->finishes() && $target->category->finishes()) {
                    $t->forceFill(['completed_at' => CarbonImmutable::now()])->save();
                    $this->record($t, TaskEvent::Completed, $by, ['column' => $target->name]);
                } elseif ($from->category->finishes() && ! $target->category->finishes()) {
                    $t->forceFill(['completed_at' => null])->save();
                    $this->record($t, TaskEvent::Reopened, $by, ['column' => $target->name]);
                }
            }
        });
    }

    public function delete(Task $task): void
    {
        $this->locked($task, function (Task $t): void {
            $column = $t->column_id;
            $t->delete();
            $this->renumber(Task::query()->where('column_id', $column)->orderBy('position')->orderBy('id')->lockForUpdate()->pluck('id')->all());
        }, refresh: false);
    }

    public function addChecklistItem(Task $task, string $title, User $by): Task
    {
        return $this->locked($task, function (Task $t) use ($title, $by): void {
            $position = (int) TaskChecklistItem::query()->where('task_id', $t->id)->max('position') + 1;
            TaskChecklistItem::query()->create(['task_id' => $t->id, 'title' => $title, 'position' => $position]);
            $this->record($t, TaskEvent::ChecklistChanged, $by, ['added' => $title]);
        });
    }

    /** @param  array<string, mixed>  $data  title, is_done */
    public function updateChecklistItem(TaskChecklistItem $item, array $data, User $by): Task
    {
        $task = Task::query()->findOrFail($item->task_id);

        return $this->locked($task, function (Task $t) use ($item, $data, $by): void {
            $item->fill(array_intersect_key($data, array_flip(['title'])));
            if (array_key_exists('is_done', $data) && (bool) $data['is_done'] !== $item->is_done) {
                $item->forceFill(['is_done' => (bool) $data['is_done'], 'done_by' => $data['is_done'] ? $by->id : null,
                    'done_at' => $data['is_done'] ? CarbonImmutable::now() : null]);
                $this->record($t, TaskEvent::ChecklistChanged, $by, [$data['is_done'] ? 'done' : 'undone' => $item->title]);
            }
            $item->save();
        });
    }

    public function deleteChecklistItem(TaskChecklistItem $item, User $by): Task
    {
        $task = Task::query()->findOrFail($item->task_id);

        return $this->locked($task, function (Task $t) use ($item, $by): void {
            $item->delete();
            $this->record($t, TaskEvent::ChecklistChanged, $by, ['removed' => $item->title]);
        });
    }

    public function comment(Task $task, string $body, User $by): TaskComment
    {
        return DB::transaction(function () use ($task, $body, $by): TaskComment {
            $this->boards->writable($this->boards->lock($task->board));
            $comment = TaskComment::query()->create(['task_id' => $task->id, 'user_id' => $by->id, 'body' => $body]);
            $this->record($task, TaskEvent::Commented, $by);
            $recipients = $task->watchers()->get()->push($task->assignee)->filter()->unique('id')->reject(fn (User $u) => $u->id === $by->id);
            Notification::send($recipients, new TaskNotification('commented', $task->id, $task->number, $task->title, $by->name));

            return $comment;
        });
    }

    /** @param  list<UploadedFile>  $uploads */
    public function attach(Task $task, array $uploads, User $by): Task
    {
        $this->boards->writable($task->board);
        $stored = array_map(fn (UploadedFile $u) => $this->files->store($u, $task->organization_id, $by), $uploads);

        return $this->locked($task, function (Task $t) use ($stored, $by): void {
            foreach ($stored as $file) {
                TaskAttachment::query()->create(['task_id' => $t->id, 'file_id' => $file->id, 'uploaded_by' => $by->id]);
            }
            $this->record($t, TaskEvent::FileAdded, $by, ['files' => array_map(fn (StoredFile $f) => $f->original_name, $stored)]);
        });
    }

    /** FileAccess resolver: attachments follow the task's board visibility. */
    public function canDownload(User $user, StoredFile $file): ?bool
    {
        $taskId = TaskAttachment::query()->where('file_id', $file->id)->value('task_id');
        if ($taskId === null) {
            return null;
        }

        return $user->can('tasks.view') && Task::query()->visibleTo($user)->whereKey($taskId)->exists();
    }

    /** tasks.manage, the responsible person or the creator. */
    public function canEdit(Task $task, User $user): bool
    {
        return $user->can('tasks.manage') || $task->assignee_id === $user->id || $task->created_by === $user->id;
    }

    /** The new responsible person hears about it (not when they assigned themselves). */
    private function notifyAssigned(Task $task, ?User $by): void
    {
        $assignee = $task->assignee_id === null ? null : User::query()->withoutGlobalScopes()->find($task->assignee_id);
        if ($assignee !== null && $assignee->id !== $by?->id) {
            $assignee->notify(new TaskNotification('assigned', $task->id, $task->number, $task->title, (string) $by?->name));
        }
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

    /**
     * Runs $work on the task re-read under the board lock (the board must not be archived).
     *
     * @param  callable(Task, TaskBoard): void  $work
     */
    private function locked(Task $task, callable $work, bool $refresh = true): Task
    {
        return DB::transaction(function () use ($task, $work, $refresh): Task {
            $board = $this->boards->lock($task->board);
            $this->boards->writable($board);
            $locked = Task::query()->lockForUpdate()->findOrFail($task->id);
            $work($locked, $board);

            return $refresh ? $locked->refresh() : $locked;
        }, 3);
    }

    /** @param  array<int, int|string>  $ids  in their new order */
    private function renumber(array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            Task::query()->whereKey((int) $id)->where('position', '!=', $i + 1)->update(['position' => $i + 1]);
        }
    }
}
