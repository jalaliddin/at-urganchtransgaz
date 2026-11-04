<?php

namespace App\Notifications\Helpdesk;

use App\Models\Ticket;

/** A new ticket arrived in an organization the recipient dispatches for. */
class TicketCreatedNotification extends TicketNotification
{
    public string $priority;

    public string $organization;

    public function __construct(Ticket $ticket)
    {
        parent::__construct($ticket);
        $this->priority = $ticket->priority->value;
        $this->organization = (string) $ticket->organization()->value('name');
    }

    public function type(): string
    {
        return 'ticket_created';
    }

    protected function params(): array
    {
        return ['priority' => $this->priority, 'organization' => $this->organization];
    }

    protected function mailParams(string $locale): array
    {
        return [...parent::mailParams($locale), 'priority' => __('enums.ticket_priority.'.$this->priority, [], $locale)];
    }
}
