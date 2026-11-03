<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;

/**
 * The actions the UI may offer for a ticket — computed on the server so the
 * client never guesses permissions or workflow rules.
 */
class TicketAbilities
{
    public function __construct(private readonly TicketWorkflow $workflow) {}

    /** @return list<string> */
    public function for(Ticket $ticket, User $user): array
    {
        $handler = $this->workflow->isHandler($ticket, $user);
        $requester = $ticket->isRequester($user);
        $open = $ticket->status->isOpen();
        $finished = in_array($ticket->status, [TicketStatus::Closed, TicketStatus::Rejected], true);

        $abilities = [
            'update' => $user->can('update', $ticket),
            'comment' => ($handler || $requester) && ! $finished,
            'comment_internal' => $handler,
            'assign' => $open && $user->can('assign', $ticket),
            'take' => $open && $handler && $ticket->assignee_id !== $user->id,
            'log_time' => $handler,
            'link' => $handler,
            'attach' => ($handler || $requester) && ! $finished,
            'rate' => $requester && in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true),
        ];

        return array_keys(array_filter($abilities));
    }

    /** @return list<string> */
    public function transitions(Ticket $ticket, User $user): array
    {
        return $this->workflow->allowedTargets($ticket, $user);
    }
}
