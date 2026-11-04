<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Helpdesk report figures over the tickets a user can see, with the same
 * filters as the ticket list. Uses Ticket::visibleTo, so it also works in jobs.
 */
class TicketStats
{
    public function __construct(private readonly BusinessCalendar $calendar) {}

    /**
     * @param  array<string, mixed>  $filters  TicketFilters names
     * @return array<string, mixed>
     */
    public function summary(User $user, array $filters = []): array
    {
        $base = fn (): Builder => $this->base($user, $filters);
        $now = CarbonImmutable::now();

        $byStatus = $this->zeroFilled($base()->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status'), TicketStatus::cases());
        $byPriority = $this->zeroFilled($base()->select('priority', DB::raw('count(*) as c'))->groupBy('priority')->pluck('c', 'priority'), TicketPriority::cases());

        $open = fn (Builder $q): Builder => $q->whereIn('status', TicketStatus::openValues());
        $overdue = fn (Builder $q): Builder => $open($q)->whereNull('paused_at')->where('resolution_due_at', '<', $now);

        $categories = $base()->join('ticket_categories as c', 'c.id', '=', 'helpdesk_tickets.category_id')
            ->select('c.id', 'c.name', 'c.name_ru', DB::raw('count(*) as total'))
            ->groupBy('c.id', 'c.name', 'c.name_ru')->orderByDesc('total')->orderBy('c.name')->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => app()->getLocale() === 'ru' && $row->name_ru ? (string) $row->name_ru : (string) $row->name,
                'total' => (int) $row->total,
            ])->all();

        $organizations = $base()->join('organizations as o', 'o.id', '=', 'helpdesk_tickets.organization_id')
            ->select('o.id', 'o.name', DB::raw('count(*) as total'),
                DB::raw("sum(case when helpdesk_tickets.status in ('".implode("','", TicketStatus::openValues())."') then 1 else 0 end) as open_count"),
                DB::raw("sum(case when helpdesk_tickets.status in ('".implode("','", TicketStatus::openValues())."') and helpdesk_tickets.paused_at is null and helpdesk_tickets.resolution_due_at < ? then 1 else 0 end) as overdue_count"))
            ->addBinding($now->toDateTimeString(), 'select')
            ->groupBy('o.id', 'o.name')->orderByDesc('total')->orderBy('o.name')->limit(20)->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id, 'name' => (string) $row->name, 'total' => (int) $row->total,
                'open' => (int) $row->open_count, 'overdue' => (int) $row->overdue_count,
            ])->all();

        $resolved = $base()->whereNotNull('resolved_at');

        return [
            'total' => array_sum($byStatus),
            'open' => $open($base())->count(),
            'overdue' => $overdue($base())->count(),
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'by_category' => $categories,
            'by_organization' => $organizations,
            'sla' => [
                'response_met' => $base()->whereNotNull('first_response_at')->whereColumn('first_response_at', '<=', 'response_due_at')->count(),
                'response_total' => $base()->where(fn ($q) => $q->whereNotNull('first_response_at')->orWhereNotNull('response_breached_at'))->count(),
                'resolution_met' => (clone $resolved)->whereColumn('resolved_at', '<=', 'resolution_due_at')->count(),
                'resolution_total' => (clone $resolved)->count(),
            ],
            'avg_resolution_minutes' => $this->averageResolution(clone $resolved),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public function base(User $user, array $filters): Builder
    {
        $query = Ticket::query()->visibleTo($user);
        TicketFilters::applyAll($query, $filters, $user);

        return $query;
    }

    /** @param  Builder<Ticket>  $resolved */
    private function averageResolution(Builder $resolved): ?int
    {
        $total = 0;
        $count = 0;
        $resolved->select(['id', 'created_at', 'resolved_at', 'paused_minutes'])
            ->chunkById(500, function ($rows) use (&$total, &$count): void {
                foreach ($rows as $ticket) {
                    $total += max(0, $this->calendar->minutesBetween($ticket->created_at, $ticket->resolved_at) - $ticket->paused_minutes);
                    $count++;
                }
            });

        return $count === 0 ? null : (int) round($total / $count);
    }

    /**
     * @param  \Illuminate\Support\Collection<array-key, mixed>  $counts
     * @param  array<int, \BackedEnum>  $cases
     * @return array<string, int>
     */
    private function zeroFilled($counts, array $cases): array
    {
        $result = [];
        foreach ($cases as $case) {
            $result[(string) $case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }
}
