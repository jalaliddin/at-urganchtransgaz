<?php

namespace App\Notifications\Helpdesk;

use App\Models\Ticket;

/** Someone commented on a ticket the recipient follows. */
class TicketCommentedNotification extends TicketNotification
{
    public function __construct(Ticket $ticket, public string $actorName, public bool $internal)
    {
        parent::__construct($ticket);
    }

    public function type(): string
    {
        return 'ticket_commented';
    }

    protected function params(): array
    {
        return ['actor' => $this->actorName, 'internal' => $this->internal];
    }
}
