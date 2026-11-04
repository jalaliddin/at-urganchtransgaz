<?php

namespace App\Services\Exports;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Helpdesk\TicketFilters;
use App\Support\Search\SearchTerm;
use Carbon\CarbonInterface;

/** Helpdesk ticket list export (same filters and visibility as the list screen). */
class TicketReport implements ReportDefinition
{
    public function permission(): string
    {
        return 'helpdesk.export';
    }

    public function filterNames(): array
    {
        return [...TicketFilters::NAMES, 'search'];
    }

    public function title(string $locale): string
    {
        return __('exports.helpdesk_tickets.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(
            fn (string $key): string => __("exports.helpdesk_tickets.columns.{$key}", [], $locale),
            ['number', 'created_at', 'title', 'organization', 'category', 'priority', 'status', 'requester', 'assignee',
                'response_due_at', 'resolution_due_at', 'resolved_at', 'sla', 'time_spent', 'rating'],
        );
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $query = Ticket::query()->visibleTo($user)
            ->with(['organization', 'category', 'assignee', 'requesterEmployee', 'requesterUser'])
            ->orderByDesc('created_at')->orderByDesc('id');
        TicketFilters::applyAll($query, $filters, $user);
        if (isset($filters['search']) && is_string($filters['search'])) {
            SearchTerm::whereContains($query, ['number', 'title', 'contact_name', 'contact_phone'], SearchTerm::normalize($filters['search']));
        }

        foreach ($query->lazyById(500, 'helpdesk_tickets.id', 'id') as $ticket) {
            /** @var Ticket $ticket */
            $breached = $ticket->resolution_breached_at !== null || $ticket->response_breached_at !== null;
            yield [
                $ticket->number,
                $this->date($ticket->created_at),
                $ticket->title,
                $ticket->organization?->name,
                $ticket->category?->localizedName($locale),
                __('enums.ticket_priority.'.$ticket->priority->value, [], $locale),
                __('enums.ticket_status.'.$ticket->status->value, [], $locale),
                $ticket->requesterEmployee->full_name ?? $ticket->requesterUser->name ?? $ticket->contact_name,
                $ticket->assignee?->name,
                $this->date($ticket->response_due_at),
                $this->date($ticket->resolution_due_at),
                $this->date($ticket->resolved_at),
                __($breached ? 'exports.sla_breached' : 'exports.sla_ok', [], $locale),
                $ticket->time_spent_minutes,
                $ticket->rating,
            ];
        }
    }

    private function date(?CarbonInterface $value): ?string
    {
        return $value?->copy()->setTimezone((string) config('app.display_timezone'))->format('d.m.Y H:i');
    }
}
