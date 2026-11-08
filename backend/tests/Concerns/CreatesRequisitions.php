<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Requisitions\RequisitionService;

/** Requisition fixtures. Requires InteractsWithAccess and CreatesWarehouse. */
trait CreatesRequisitions
{
    /**
     * @param  list<array<string, mixed>>|null  $items
     * @param  array<string, mixed>  $attributes
     */
    protected function draftFor(Organization $organization, User $requester, ?array $items = null, array $attributes = []): Requisition
    {
        return app(RequisitionService::class)->create([
            'organization_id' => $organization->id, 'title' => 'Kartrijlar va monitor', 'description' => 'Bo\'lim uchun', ...$attributes,
        ], $items ?? [
            ['kind' => 'consumable', 'name' => 'HP 85A kartrij', 'quantity' => 4],
            ['kind' => 'equipment', 'name' => 'Monitor 24"', 'quantity' => 1],
        ], $requester);
    }
}
