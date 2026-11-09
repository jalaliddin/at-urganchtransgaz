<?php

namespace App\Http\Resources\Tasks;

use App\Http\Resources\Warehouse\StockResource;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskBoard;
use App\Models\TaskChecklistItem;
use App\Models\TaskColumn;
use App\Models\TaskComment;
use App\Models\TaskHistory;
use App\Models\TaskLabel;
use App\Models\User;

/** Plain arrays for boards and tasks. */
final class TaskResource
{
    public const CARD_RELATIONS = ['assignee', 'labels', 'organization', 'column'];

    /** @return array<string, mixed> */
    public static function board(TaskBoard $b): array
    {
        return [
            'id' => $b->id, 'name' => $b->name, 'description' => $b->description, 'is_archived' => $b->is_archived,
            'organization' => ['id' => $b->organization->id, 'name' => $b->organization->name],
            'open_tasks' => array_key_exists('open_tasks', $b->getAttributes()) ? (int) $b->getAttribute('open_tasks') : null,
            'overdue_tasks' => array_key_exists('overdue_tasks', $b->getAttributes()) ? (int) $b->getAttribute('overdue_tasks') : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function column(TaskColumn $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'category' => $c->category->value, 'position' => $c->position, 'wip_limit' => $c->wip_limit];
    }

    /** @return array<string, mixed> */
    public static function label(TaskLabel $l): array
    {
        return ['id' => $l->id, 'name' => $l->name, 'color' => $l->color];
    }

    /** @return array<string, mixed>|null */
    public static function user(?User $u): ?array
    {
        return $u ? ['id' => $u->id, 'name' => $u->name] : null;
    }

    /** @return array<string, mixed> */
    public static function card(Task $t): array
    {
        return [
            'id' => $t->id, 'number' => $t->number, 'title' => $t->title, 'board_id' => $t->board_id, 'column_id' => $t->column_id,
            'position' => $t->position, 'priority' => $t->priority->value, 'project' => $t->project,
            'organization' => ['id' => $t->organization->id, 'name' => $t->organization->name],
            'assignee' => self::user($t->assignee), 'start_date' => $t->start_date?->toDateString(), 'due_date' => $t->due_date?->toDateString(),
            'completed_at' => $t->completed_at?->toIso8601String(), 'is_overdue' => $t->isOverdue(),
            'labels' => $t->labels->map(fn (TaskLabel $l) => self::label($l))->values(),
            'checklist_total' => array_key_exists('checklist_total', $t->getAttributes()) ? (int) $t->getAttribute('checklist_total') : null,
            'checklist_done' => array_key_exists('checklist_done', $t->getAttributes()) ? (int) $t->getAttribute('checklist_done') : null,
            'comments_count' => array_key_exists('comments_count', $t->getAttributes()) ? (int) $t->getAttribute('comments_count') : null,
        ];
    }

    /**
     * @param  array<string, bool>  $can
     * @return array<string, mixed>
     */
    public static function detail(Task $t, array $can): array
    {
        return [
            ...self::card($t),
            'description' => $t->description,
            'board' => ['id' => $t->board->id, 'name' => $t->board->name, 'is_archived' => $t->board->is_archived],
            'column' => self::column($t->column),
            'creator' => self::user($t->creator),
            'ticket' => $t->ticket ? ['id' => $t->ticket->id, 'number' => $t->ticket->number, 'title' => $t->ticket->title] : null,
            'watchers' => $t->watchers->map(fn (User $u) => self::user($u))->values(),
            'checklist' => $t->checklist->map(fn (TaskChecklistItem $i) => [
                'id' => $i->id, 'title' => $i->title, 'is_done' => $i->is_done, 'position' => $i->position, 'done_at' => $i->done_at?->toIso8601String(),
            ])->values(),
            'comments' => $t->comments->map(fn (TaskComment $c) => [
                'id' => $c->id, 'body' => $c->body, 'user' => self::user($c->user), 'created_at' => $c->created_at->toIso8601String(),
            ])->values(),
            'attachments' => $t->attachments->map(fn (TaskAttachment $a) => [
                'id' => $a->id, 'file' => StockResource::file($a->file), 'user' => self::user($a->uploader), 'created_at' => $a->created_at->toIso8601String(),
            ])->values(),
            'history' => $t->histories->map(fn (TaskHistory $h) => [
                'id' => $h->id, 'event' => $h->event->value, 'payload' => $h->payload ?? (object) [], 'user' => self::user($h->user),
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values(),
            'created_at' => $t->created_at->toIso8601String(),
            'can' => $can,
        ];
    }
}
