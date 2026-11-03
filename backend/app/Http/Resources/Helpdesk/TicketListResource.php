<?php

namespace App\Http\Resources\Helpdesk;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketListResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->resource;

        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'title' => $ticket->title,
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'category' => $ticket->relationLoaded('category') && $ticket->category
                ? ['id' => $ticket->category->id, 'name' => $ticket->category->localizedName()]
                : null,
            'organization' => $ticket->relationLoaded('organization') && $ticket->organization
                ? ['id' => $ticket->organization->id, 'name' => $ticket->organization->name]
                : null,
            'requester' => [
                'employee_id' => $ticket->requester_employee_id,
                'user_id' => $ticket->requester_user_id,
                'name' => $this->requesterName($ticket),
            ],
            'assignee' => $ticket->relationLoaded('assignee') && $ticket->assignee
                ? ['id' => $ticket->assignee->id, 'name' => $ticket->assignee->name]
                : null,
            'created_at' => $this->iso($ticket->created_at),
            'response_due_at' => $this->iso($ticket->response_due_at),
            'resolution_due_at' => $this->iso($ticket->resolution_due_at),
            'first_response_at' => $this->iso($ticket->first_response_at),
            'paused_at' => $this->iso($ticket->paused_at),
            'resolved_at' => $this->iso($ticket->resolved_at),
            'closed_at' => $this->iso($ticket->closed_at),
            'response_breached' => $ticket->response_breached_at !== null,
            'resolution_breached' => $ticket->resolution_breached_at !== null,
        ];
    }

    protected function requesterName(Ticket $ticket): ?string
    {
        if ($ticket->relationLoaded('requesterEmployee') && $ticket->requesterEmployee) {
            return $ticket->requesterEmployee->full_name;
        }
        if ($ticket->relationLoaded('requesterUser') && $ticket->requesterUser) {
            return $ticket->requesterUser->name;
        }

        return $ticket->contact_name;
    }
}
