<?php

namespace App\Notifications\Helpdesk;

use App\Models\Ticket;

/** The recipient was made responsible for a ticket. */
class TicketAssignedNotification extends TicketNotification
{
    public function __construct(Ticket $ticket, public string $actorName)
    {
        parent::__construct($ticket);
    }

    public function type(): string
    {
        return 'ticket_assigned';
    }

    protected function params(): array
    {
        return ['actor' => $this->actorName];
    }
}
