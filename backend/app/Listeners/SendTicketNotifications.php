<?php

namespace App\Listeners;

use App\Enums\TicketStatus;
use App\Events\Helpdesk\TicketAssigned;
use App\Events\Helpdesk\TicketCommented;
use App\Events\Helpdesk\TicketCreated;
use App\Events\Helpdesk\TicketStatusChanged;
use App\Models\User;
use App\Notifications\Helpdesk\TicketAssignedNotification;
use App\Notifications\Helpdesk\TicketCommentedNotification;
use App\Notifications\Helpdesk\TicketCreatedNotification;
use App\Notifications\Helpdesk\TicketStatusChangedNotification;
use App\Services\Helpdesk\HelpdeskRecipients;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about what (spec §5.14): dispatchers about new tickets, assignees
 * about assignments and requester replies, requesters about progress. The actor
 * is never notified about their own action.
 */
class SendTicketNotifications
{
    public function __construct(private readonly HelpdeskRecipients $recipients) {}

    public function handleCreated(TicketCreated $event): void
    {
        $users = $this->recipients->dispatchers($event->ticket)->reject(fn (User $u) => $u->id === $event->actor->id);
        Notification::send($users, new TicketCreatedNotification($event->ticket));
    }

    public function handleAssigned(TicketAssigned $event): void
    {
        if ($event->assignee->id !== $event->actor->id) {
            $event->assignee->notify(new TicketAssignedNotification($event->ticket, $event->actor->name));
        }
    }

    public function handleStatusChanged(TicketStatusChanged $event): void
    {
        $ticket = $event->ticket;
        $actorId = $event->actor?->id;
        $byRequester = $event->actor !== null && $ticket->isRequester($event->actor);

        $recipient = $byRequester ? $this->user($ticket->assignee_id) : $this->user($ticket->requester_user_id);
        $reopened = $event->to === TicketStatus::InProgress && in_array($event->from, [TicketStatus::Resolved, TicketStatus::Closed], true);
        if ($byRequester && ! $reopened && $event->to !== TicketStatus::Closed) {
            return;
        }
        if ($recipient !== null && $recipient->id !== $actorId) {
            $recipient->notify(new TicketStatusChangedNotification($ticket, $event->actor?->name));
        }
    }

    public function handleCommented(TicketCommented $event): void
    {
        $ticket = $event->ticket;
        $author = $event->actor;
        $internal = $event->comment->is_internal;

        if ($internal || $ticket->isRequester($author)) {
            $recipients = $ticket->assignee_id !== null
                ? collect([$this->user($ticket->assignee_id)])->filter()
                : ($internal ? collect() : $this->recipients->dispatchers($ticket));
        } else {
            $recipients = collect([$this->user($ticket->requester_user_id)])->filter();
        }

        Notification::send(
            $recipients->reject(fn (User $u) => $u->id === $author->id)->values(),
            new TicketCommentedNotification($ticket, $author->name, $internal),
        );
    }

    private function user(?int $id): ?User
    {
        return $id === null ? null : User::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('is_active', true)->find($id);
    }
}
