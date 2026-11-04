<?php

namespace App\Events\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class TicketStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Ticket $ticket, public readonly TicketStatus $from, public readonly TicketStatus $to, public readonly ?User $actor) {}
}
