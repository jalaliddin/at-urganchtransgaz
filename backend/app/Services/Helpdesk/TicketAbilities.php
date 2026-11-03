<?php

namespace App\Services\Helpdesk;

use App\Models\Ticket;
use App\Models\User;

/**
 * The actions the UI may offer for a ticket — computed on the server so the
 * client never guesses permissions or workflow rules.
 */
class TicketAbilities
{
    /** @return list<string> */
    public function for(Ticket $ticket, User $user): array
    {
        $actions = [];
        if ($user->can('update', $ticket)) {
            $actions[] = 'update';
        }

        return $actions;
    }
}
