<?php

namespace App\Events\Helpdesk;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class TicketCommented implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Ticket $ticket, public readonly TicketComment $comment, public readonly User $actor) {}
}
