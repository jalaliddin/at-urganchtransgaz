<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('helpdesk.use');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->isRequester($user)
            || $ticket->isCreator($user)
            || ($user->can('helpdesk.view') && $user->canAccessOrganization($ticket->organization_id));
    }

    public function create(User $user): bool
    {
        return $user->can('helpdesk.create');
    }

    /** Requesters edit until triage starts; handlers while the ticket is open. */
    public function update(User $user, Ticket $ticket): bool
    {
        return ($ticket->isRequester($user) && $ticket->status === TicketStatus::New)
            || ($this->process($user, $ticket) && $ticket->status->isOpen());
    }

    public function process(User $user, Ticket $ticket): bool
    {
        return $user->can('helpdesk.process') && $user->canAccessOrganization($ticket->organization_id);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->can('helpdesk.assign') && $user->canAccessOrganization($ticket->organization_id);
    }
}
