<?php

namespace App\Http\Resources\Helpdesk;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Helpdesk\TicketAbilities;
use Illuminate\Http\Request;

/** @mixin Ticket */
class TicketResource extends TicketListResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->resource;
        /** @var User $user */
        $user = $request->user();

        return [
            ...parent::toArray($request),
            'description' => $ticket->description,
            'department' => $ticket->relationLoaded('department') && $ticket->department
                ? ['id' => $ticket->department->id, 'name' => $ticket->department->name]
                : null,
            'contact_name' => $ticket->contact_name,
            'contact_phone' => $ticket->contact_phone,
            'contact_email' => $ticket->contact_email,
            'location' => $ticket->location,
            'assigned_at' => $this->iso($ticket->assigned_at),
            'sla_response_minutes' => $ticket->sla_response_minutes,
            'sla_resolution_minutes' => $ticket->sla_resolution_minutes,
            'paused_minutes' => $ticket->paused_minutes,
            'rejected_at' => $this->iso($ticket->rejected_at),
            'resolution' => $ticket->resolution,
            'reject_reason' => $ticket->reject_reason,
            'rating' => $ticket->rating,
            'rating_comment' => $ticket->rating_comment,
            'reopened_count' => $ticket->reopened_count,
            'time_spent_minutes' => $ticket->time_spent_minutes,
            'is_requester' => $ticket->isRequester($user),
            'allowed_actions' => app(TicketAbilities::class)->for($ticket, $user),
        ];
    }
}
