<?php

namespace App\Events\Helpdesk;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class TicketAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Ticket $ticket, public readonly User $assignee, public readonly User $actor) {}
}
