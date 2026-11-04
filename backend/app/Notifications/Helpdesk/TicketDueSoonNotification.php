<?php

namespace App\Notifications\Helpdesk;

use App\Models\Ticket;
use Carbon\CarbonImmutable;

/** The resolution deadline of the recipient's ticket is near. */
class TicketDueSoonNotification extends TicketNotification
{
    public string $dueAt;

    public function __construct(Ticket $ticket)
    {
        parent::__construct($ticket);
        $this->dueAt = $ticket->resolution_due_at->toIso8601String();
    }

    public function type(): string
    {
        return 'ticket_due_soon';
    }

    protected function params(): array
    {
        return ['due_at' => $this->dueAt];
    }

    protected function mailParams(string $locale): array
    {
        $local = CarbonImmutable::parse($this->dueAt)->setTimezone((string) config('app.display_timezone'));

        return [...parent::mailParams($locale), 'due_at' => $local->format('d.m.Y H:i')];
    }
}
