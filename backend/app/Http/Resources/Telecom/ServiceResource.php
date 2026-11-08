<?php

namespace App\Http\Resources\Telecom;

use App\Models\ItService;
use App\Models\ServiceContract;

/** Plain arrays for contracts and services. */
final class ServiceResource
{
    /** @return array<string, mixed> */
    public static function contract(ServiceContract $c): array
    {
        return [
            'id' => $c->id, 'number' => $c->number, 'subject' => $c->subject,
            'provider' => ['id' => $c->provider->id, 'name' => $c->provider->name],
            'organization' => ['id' => $c->organization->id, 'name' => $c->organization->name],
            'signed_at' => $c->signed_at?->toDateString(), 'valid_from' => $c->valid_from?->toDateString(),
            'valid_to' => $c->valid_to?->toDateString(), 'terminated_at' => $c->terminated_at?->toDateString(),
            'amount' => $c->amount, 'note' => $c->note, 'status' => $c->statusOn(),
            'services_count' => array_key_exists('services_count', $c->getAttributes()) ? (int) $c->getAttribute('services_count') : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function service(ItService $s): array
    {
        return [
            'id' => $s->id, 'type' => $s->type->value, 'name' => $s->name,
            'provider' => ['id' => $s->provider->id, 'name' => $s->provider->name],
            'organization' => ['id' => $s->organization->id, 'name' => $s->organization->name],
            'contract' => $s->contract ? ['id' => $s->contract->id, 'number' => $s->contract->number] : null,
            'address' => $s->address, 'identifier' => $s->identifier, 'tariff' => $s->tariff, 'fee' => $s->fee,
            'billing_cycle' => $s->billing_cycle->value, 'due_day' => $s->due_day,
            'started_at' => $s->started_at?->toDateString(), 'ended_at' => $s->ended_at?->toDateString(),
            'is_active' => $s->is_active, 'note' => $s->note,
        ];
    }
}
