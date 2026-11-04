<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Support\QueryFilters\OrganizationSubtreeFilter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Throwable;

/**
 * Ticket list filters shared by the list endpoint (spatie query builder) and
 * queued exports (plain arrays), so both always select the same rows.
 */
final class TicketFilters
{
    public const NAMES = [
        'status', 'priority', 'category', 'organization', 'assignee', 'requester', 'overdue', 'created_from', 'created_to',
    ];

    /** @return list<AllowedFilter> */
    public static function allowed(User $user): array
    {
        return array_map(
            fn (string $name): AllowedFilter => AllowedFilter::callback(
                $name,
                fn (Builder $query, mixed $value) => self::apply($query, $name, $value, $user),
            ),
            self::NAMES,
        );
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyAll(Builder $query, array $filters, User $user): void
    {
        foreach (array_intersect_key($filters, array_flip(self::NAMES)) as $name => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                self::apply($query, (string) $name, $value, $user);
            }
        }
    }

    /** @param  Builder<Ticket>  $query */
    public static function apply(Builder $query, string $name, mixed $value, User $user): void
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);
        $values = array_values(array_filter(array_map(fn ($v) => is_bool($v) ? $v : trim((string) $v), $values), fn ($v) => $v !== ''));

        match ($name) {
            'status' => self::whereInOrNone($query, 'status', array_filter($values, fn ($v) => TicketStatus::tryFrom((string) $v) !== null)),
            'priority' => self::whereInOrNone($query, 'priority', array_filter($values, fn ($v) => TicketPriority::tryFrom((string) $v) !== null)),
            'category' => self::whereInOrNone($query, 'category_id', array_filter(array_map('intval', $values))),
            'organization' => (new OrganizationSubtreeFilter)($query, $values[0] ?? null, 'organization'),
            'assignee' => self::assignee($query, (string) ($values[0] ?? ''), $user),
            'requester' => ($values[0] ?? null) === 'me' ? self::requestedBy($query, $user) : $query->whereRaw('1 = 0'),
            'overdue' => self::overdue($query, filter_var($values[0] ?? false, FILTER_VALIDATE_BOOL)),
            'created_from' => self::createdBound($query, (string) ($values[0] ?? ''), '>='),
            'created_to' => self::createdBound($query, (string) ($values[0] ?? ''), '<'),
            default => null,
        };
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<int, mixed>  $values
     */
    private static function whereInOrNone(Builder $query, string $column, array $values): void
    {
        $values === [] ? $query->whereRaw('1 = 0') : $query->whereIn($query->qualifyColumn($column), array_values($values));
    }

    /** @param  Builder<Ticket>  $query */
    private static function assignee(Builder $query, string $value, User $user): void
    {
        match (true) {
            $value === 'me' => $query->where($query->qualifyColumn('assignee_id'), $user->id),
            $value === 'none' => $query->whereNull($query->qualifyColumn('assignee_id')),
            ctype_digit($value) => $query->where($query->qualifyColumn('assignee_id'), (int) $value),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** @param  Builder<Ticket>  $query */
    private static function requestedBy(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user): void {
            $q->where($q->qualifyColumn('requester_user_id'), $user->id)->orWhere($q->qualifyColumn('created_by'), $user->id);
            if ($user->employee_id !== null) {
                $q->orWhere($q->qualifyColumn('requester_employee_id'), $user->employee_id);
            }
        });
    }

    /** @param  Builder<Ticket>  $query */
    private static function overdue(Builder $query, bool $overdue): void
    {
        if (! $overdue) {
            return;
        }
        $query->whereIn($query->qualifyColumn('status'), TicketStatus::openValues())
            ->whereNull($query->qualifyColumn('paused_at'))
            ->where($query->qualifyColumn('resolution_due_at'), '<', now());
    }

    /** @param  Builder<Ticket>  $query */
    private static function createdBound(Builder $query, string $date, string $operator): void
    {
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, (string) config('app.display_timezone'));
        } catch (Throwable) {
            $day = null;
        }
        if (! $day instanceof CarbonImmutable) {
            $query->whereRaw('1 = 0');

            return;
        }
        $bound = $operator === '<' ? $day->addDay() : $day;
        $query->where($query->qualifyColumn('created_at'), $operator, $bound->utc());
    }
}
