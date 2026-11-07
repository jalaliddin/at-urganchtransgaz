<?php

namespace App\Http\Resources\Assets;

use App\Models\AssetCategory;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\Manufacturer;

/** Plain arrays for asset reference data (small, stable shapes). */
final class CatalogResource
{
    /** @return array<string, mixed> */
    public static function category(AssetCategory $c): array
    {
        return [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'name_ru' => $c->name_ru,
            'label' => $c->localizedName(), 'is_networked' => $c->is_networked, 'is_active' => $c->is_active,
            'sort_order' => $c->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    public static function manufacturer(Manufacturer $m): array
    {
        return ['id' => $m->id, 'name' => $m->name, 'website' => $m->website];
    }

    /** @return array<string, mixed> */
    public static function model(AssetModel $m): array
    {
        return [
            'id' => $m->id, 'name' => $m->name, 'specs' => $m->specs,
            'manufacturer' => ['id' => $m->manufacturer->id, 'name' => $m->manufacturer->name],
            'category' => ['id' => $m->category->id, 'name' => $m->category->localizedName()],
        ];
    }

    /** @return array<string, mixed> */
    public static function location(Location $l): array
    {
        return [
            'id' => $l->id, 'name' => $l->name, 'full_name' => $l->fullName(), 'code' => $l->code,
            'description' => $l->description, 'is_active' => $l->is_active,
            'organization' => $l->organization ? ['id' => $l->organization->id, 'name' => $l->organization->name] : null,
            'parent' => $l->parent ? ['id' => $l->parent->id, 'name' => $l->parent->name] : null,
        ];
    }
}
