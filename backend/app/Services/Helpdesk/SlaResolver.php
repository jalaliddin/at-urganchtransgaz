<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketPriority;
use App\Models\TicketSlaPolicy;

/**
 * SLA targets (business minutes) for a ticket: a category-specific policy wins,
 * then the priority-wide policy, then config('helpdesk.default_sla').
 */
class SlaResolver
{
    /** @return array{response: int, resolution: int} */
    public function for(TicketPriority $priority, ?int $categoryId): array
    {
        $policies = TicketSlaPolicy::query()
            ->where('priority', $priority->value)
            ->where(fn ($q) => $q->whereNull('category_id')->when($categoryId, fn ($q) => $q->orWhere('category_id', $categoryId)))
            ->get();

        $policy = $policies->firstWhere('category_id', $categoryId) ?? $policies->firstWhere('category_id', null);
        if ($policy instanceof TicketSlaPolicy) {
            return ['response' => $policy->response_minutes, 'resolution' => $policy->resolution_minutes];
        }

        /** @var array{int, int} $default */
        $default = config("helpdesk.default_sla.{$priority->value}");

        return ['response' => (int) $default[0], 'resolution' => (int) $default[1]];
    }
}
