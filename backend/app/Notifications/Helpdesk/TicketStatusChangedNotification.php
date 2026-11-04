<?php

namespace App\Notifications\Helpdesk;

use App\Models\Ticket;

/** The status of a ticket the recipient follows changed. */
class TicketStatusChangedNotification extends TicketNotification
{
    public string $status;

    public function __construct(Ticket $ticket, public ?string $actorName)
    {
        parent::__construct($ticket);
        $this->status = $ticket->status->value;
    }

    public function type(): string
    {
        return 'ticket_status_changed';
    }

    protected function params(): array
    {
        return ['status' => $this->status, 'actor' => $this->actorName];
    }

    protected function mailParams(string $locale): array
    {
        return [...parent::mailParams($locale), 'status' => __('enums.ticket_status.'.$this->status, [], $locale)];
    }
}
