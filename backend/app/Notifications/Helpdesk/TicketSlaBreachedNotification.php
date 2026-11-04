<?php

namespace App\Notifications\Helpdesk;

use App\Models\Ticket;

/** A ticket missed its response or resolution deadline. */
class TicketSlaBreachedNotification extends TicketNotification
{
    /** @param  'response'|'resolution'  $kind */
    public function __construct(Ticket $ticket, public string $kind)
    {
        parent::__construct($ticket);
    }

    public function type(): string
    {
        return 'ticket_sla_breached';
    }

    protected function params(): array
    {
        return ['kind' => $this->kind];
    }

    protected function mailParams(string $locale): array
    {
        return [...parent::mailParams($locale), 'kind' => __('notifications.sla_kind.'.$this->kind, [], $locale)];
    }
}
