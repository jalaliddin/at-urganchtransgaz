<?php

namespace App\Services\Tasks;

use App\Enums\ColumnCategory;
use App\Exceptions\DomainConflictException;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskColumn;
use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Boards and their columns. Column changes run under the board row lock and keep at least one open and one
 * done column; columns with tasks keep their category and cannot be deleted.
 */
class BoardService
{
    /** Standard columns (§5.11); names are board data in the organization's language. */
    public const DEFAULT_COLUMNS = [
        ['Backlog', ColumnCategory::Open],
        ['Rejalashtirilgan', ColumnCategory::Open],
        ['Jarayonda', ColumnCategory::Open],
        ['Tekshiruvda', ColumnCategory::Open],
        ['Bajarildi', ColumnCategory::Done],
        ['Bekor qilindi', ColumnCategory::Cancelled],
    ];

    /** @param  array<string, mixed>  $data  organization_id, name, description */
    public function create(array $data, User $by): TaskBoard
    {
        return DB::transaction(function () use ($data): TaskBoard {
            $board = TaskBoard::query()->create(array_intersect_key($data, array_flip(['organization_id', 'name', 'description'])));
            foreach (self::DEFAULT_COLUMNS as $i => [$name, $category]) {
                TaskColumn::query()->create(['board_id' => $board->id, 'name' => $name, 'category' => $category, 'position' => $i + 1]);
            }

            return $board;
        });
    }

    public function delete(TaskBoard $board): void
    {
        DB::transaction(function () use ($board): void {
            $this->lock($board);
            if (Task::query()->where('board_id', $board->id)->exists()) {
                throw new DomainConflictException(__('messages.board_has_tasks'));
            }
            // Recurrences point at columns (restrict): they go first, then columns and labels cascade.
            TaskRecurrence::query()->where('board_id', $board->id)->delete();
            $board->delete();
        });
    }

    /** @param  array<string, mixed>  $data  name, category, wip_limit */
    public function addColumn(TaskBoard $board, array $data): TaskColumn
    {
        return DB::transaction(function () use ($board, $data): TaskColumn {
            $this->writable($this->lock($board));
            $position = (int) TaskColumn::query()->where('board_id', $board->id)->max('position') + 1;

            return TaskColumn::query()->create([...$data, 'board_id' => $board->id, 'position' => $position]);
        });
    }

    /** @param  array<string, mixed>  $data  name, category, wip_limit */
    public function updateColumn(TaskColumn $column, array $data): TaskColumn
    {
        return DB::transaction(function () use ($column, $data): TaskColumn {
            $this->writable($this->lock($column->board));
            $column = TaskColumn::query()->lockForUpdate()->findOrFail($column->id);
            $category = isset($data['category']) ? ColumnCategory::from((string) $data['category']) : $column->category;
            if ($category !== $column->category) {
                // A recurrence's column must stay open, or its tasks would be born finished.
                if (Task::query()->where('column_id', $column->id)->exists()
                    || TaskRecurrence::query()->where('column_id', $column->id)->exists()) {
                    throw ValidationException::withMessages(['category' => __('messages.column_category_in_use')]);
                }
                $this->keepsOpenAndDone($column->board_id, $column->id, $category, 'category');
            }
            $column->fill($data)->save();

            return $column;
        });
    }

    public function deleteColumn(TaskColumn $column): void
    {
        DB::transaction(function () use ($column): void {
            $this->writable($this->lock($column->board));
            if (Task::query()->where('column_id', $column->id)->lockForUpdate()->exists()) {
                throw new DomainConflictException(__('messages.column_not_empty'));
            }
            if (TaskRecurrence::query()->where('column_id', $column->id)->exists()) {
                throw new DomainConflictException(__('messages.column_used_by_recurrence'));
            }
            $this->keepsOpenAndDone($column->board_id, $column->id, null, 'column');
            $column->delete();
            $this->renumber($column->board_id);
        });
    }

    /** @param  list<int>  $ids  every column of the board, in the new order */
    public function reorder(TaskBoard $board, array $ids): void
    {
        DB::transaction(function () use ($board, $ids): void {
            $this->writable($this->lock($board));
            $current = TaskColumn::query()->where('board_id', $board->id)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $given = collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($current !== $given) {
                throw ValidationException::withMessages(['ids' => __('messages.column_order_invalid')]);
            }
            foreach ($ids as $i => $id) {
                TaskColumn::query()->whereKey($id)->update(['position' => $i + 1]);
            }
        });
    }

    /** Re-reads the board under its row lock (serializes column and card moves of one board). */
    public function lock(TaskBoard $board): TaskBoard
    {
        return TaskBoard::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($board->id);
    }

    public function writable(TaskBoard $board): void
    {
        if ($board->is_archived) {
            throw new DomainConflictException(__('messages.board_archived'));
        }
    }

    /** Without $exceptId (or with it re-categorised to $as) the board must still have an open and a done column. */
    private function keepsOpenAndDone(int $boardId, int $exceptId, ?ColumnCategory $as, string $field): void
    {
        $categories = TaskColumn::query()->where('board_id', $boardId)->whereKeyNot($exceptId)->pluck('category')
            ->map(fn ($c) => $c instanceof ColumnCategory ? $c : ColumnCategory::from((string) $c))->all();
        if ($as !== null) {
            $categories[] = $as;
        }
        if (! in_array(ColumnCategory::Open, $categories, true) || ! in_array(ColumnCategory::Done, $categories, true)) {
            throw ValidationException::withMessages([$field => __('messages.column_keep_open_done')]);
        }
    }

    private function renumber(int $boardId): void
    {
        foreach (TaskColumn::query()->where('board_id', $boardId)->orderBy('position')->pluck('id')->values() as $i => $id) {
            TaskColumn::query()->whereKey($id)->update(['position' => $i + 1]);
        }
    }
}
