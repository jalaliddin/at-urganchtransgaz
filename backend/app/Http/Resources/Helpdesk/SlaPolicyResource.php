<?php

namespace App\Http\Resources\Helpdesk;

use App\Models\TicketSlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketSlaPolicy */
class SlaPolicyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var TicketSlaPolicy $policy */
        $policy = $this->resource;

        return [
            'id' => $policy->id,
            'priority' => $policy->priority->value,
            'category' => $policy->category
                ? ['id' => $policy->category->id, 'name' => $policy->category->localizedName()]
                : null,
            'response_minutes' => $policy->response_minutes,
            'resolution_minutes' => $policy->resolution_minutes,
        ];
    }
}
