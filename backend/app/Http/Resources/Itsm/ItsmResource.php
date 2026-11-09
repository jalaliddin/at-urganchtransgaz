<?php

namespace App\Http\Resources\Itsm;

use App\Models\Change;
use App\Models\ChangeHistory;
use App\Models\Organization;
use App\Models\Problem;
use App\Models\ProblemHistory;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;

/** JSON shapes of problems, changes and preventive maintenance. */
final class ItsmResource
{
    /** @return array{id: int, name: string}|null */
    public static function user(?User $u): ?array
    {
        return $u === null ? null : ['id' => $u->id, 'name' => $u->name];
    }

    /** @return array{id: int, name: string} */
    public static function organization(Organization $o): array
    {
        return ['id' => $o->id, 'name' => $o->name];
    }

    /** @return array{id: int, code: string|null, name: string}|null */
    public static function category(?TicketCategory $c): ?array
    {
        return $c === null ? null : ['id' => $c->id, 'code' => $c->code, 'name' => $c->localizedName()];
    }

    /** @return array<string, mixed> */
    public static function problem(Problem $p): array
    {
        return [
            'id' => $p->id,
            'number' => $p->number,
            'title' => $p->title,
            'organization' => self::organization($p->organization),
            'category' => self::category($p->category),
            'priority' => $p->priority->value,
            'status' => $p->status->value,
            'assignee' => self::user($p->assignee),
            'tickets_count' => (int) ($p->getAttributes()['tickets_count'] ?? 0),
            'created_at' => $p->created_at->toIso8601String(),
            'resolved_at' => $p->resolved_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $changes
     * @param  array<string, mixed>  $can
     * @return array<string, mixed>
     */
    public static function problemDetail(Problem $p, array $changes, array $can): array
    {
        return [
            ...self::problem($p),
            'description' => $p->description,
            'root_cause' => $p->root_cause,
            'workaround' => $p->workaround,
            'resolution' => $p->resolution,
            'close_reason' => $p->close_reason,
            'closed_at' => $p->closed_at?->toIso8601String(),
            'creator' => self::user($p->creator),
            'article' => $p->article === null ? null : ['id' => $p->article->id, 'number' => $p->article->number, 'title' => $p->article->title],
            'tickets' => $p->tickets->map(fn (Ticket $t) => self::ticket($t))->values()->all(),
            'changes' => $changes,
            'history' => $p->histories->sortByDesc('id')->map(fn (ProblemHistory $h) => [
                'id' => $h->id, 'event' => $h->event->value, 'payload' => $h->payload, 'comment' => $h->comment,
                'user' => self::user($h->user), 'created_at' => $h->created_at->toIso8601String(),
            ])->values()->all(),
            'can' => $can,
        ];
    }

    /** @return array<string, mixed> */
    public static function ticket(Ticket $t): array
    {
        return [
            'id' => $t->id, 'number' => $t->number, 'title' => $t->title, 'status' => $t->status->value,
            'organization' => $t->relationLoaded('organization') && $t->organization ? self::organization($t->organization) : null,
            'created_at' => $t->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function changeRef(Change $c): array
    {
        return ['id' => $c->id, 'number' => $c->number, 'title' => $c->title, 'status' => $c->status->value,
            'planned_start' => $c->planned_start?->toIso8601String()];
    }

    /** @return array<string, mixed> */
    public static function changeHistory(ChangeHistory $h): array
    {
        return ['id' => $h->id, 'event' => $h->event->value, 'payload' => $h->payload, 'comment' => $h->comment,
            'user' => self::user($h->user), 'created_at' => $h->created_at->toIso8601String()];
    }
}
