<?php

namespace App\Http\Resources\Helpdesk;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\TicketHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketHistory */
class TicketHistoryResource extends JsonResource
{
    use FormatsDates;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TicketHistory $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'action' => $entry->action->value,
            'user' => $entry->user ? ['id' => $entry->user->id, 'name' => $entry->user->name] : null,
            'old_values' => $entry->old_values,
            'new_values' => $entry->new_values,
            'note' => $entry->note,
            'is_internal' => $entry->is_internal,
            'created_at' => $this->iso($entry->created_at),
        ];
    }
}
