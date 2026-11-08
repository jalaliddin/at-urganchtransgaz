<?php

namespace App\Http\Resources\Warehouse;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Unit;
use App\Models\Warehouse;

/** Plain arrays for warehouse reference data. */
final class WarehouseCatalogResource
{
    /** @return array<string, mixed> */
    public static function unit(Unit $u): array
    {
        return [
            'id' => $u->id, 'code' => $u->code, 'name' => $u->name, 'name_ru' => $u->name_ru,
            'label' => $u->localizedName(), 'is_active' => $u->is_active, 'sort_order' => $u->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    public static function category(MaterialCategory $c): array
    {
        return [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'name_ru' => $c->name_ru,
            'label' => $c->localizedName(), 'is_active' => $c->is_active, 'sort_order' => $c->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    public static function material(Material $m): array
    {
        return [
            'id' => $m->id, 'code' => $m->code, 'name' => $m->name,
            'category' => ['id' => $m->category->id, 'name' => $m->category->localizedName()],
            'unit' => ['id' => $m->unit->id, 'name' => $m->unit->localizedName()],
            'track_batches' => $m->track_batches, 'track_expiry' => $m->track_expiry,
            'min_quantity' => $m->min_quantity, 'description' => $m->description, 'is_active' => $m->is_active,
        ];
    }

    /** @return array<string, mixed> */
    public static function warehouse(Warehouse $w): array
    {
        return [
            'id' => $w->id, 'name' => $w->name, 'code' => $w->code, 'description' => $w->description,
            'is_active' => $w->is_active,
            'organization' => ['id' => $w->organization->id, 'name' => $w->organization->name],
            'location' => $w->location ? ['id' => $w->location->id, 'name' => $w->location->fullName()] : null,
            'responsible' => $w->responsible ? ['id' => $w->responsible->id, 'name' => $w->responsible->name] : null,
        ];
    }
}
