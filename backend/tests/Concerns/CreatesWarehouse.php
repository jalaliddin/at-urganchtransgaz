<?php

namespace Tests\Concerns;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\Warehouse;
use Database\Seeders\WarehouseSeeder;

/** Warehouse fixtures. Requires InteractsWithAccess for users. */
trait CreatesWarehouse
{
    protected function seedWarehouse(): void
    {
        $this->seedRbac();
        $this->seed(WarehouseSeeder::class);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function warehouseIn(Organization $organization, array $attributes = []): Warehouse
    {
        return Warehouse::query()->withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'name' => 'Asosiy ombor '.fake()->unique()->numberBetween(1, 99999),
            ...$attributes,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function material(array $attributes = []): Material
    {
        if (Unit::query()->doesntExist()) {
            $this->seed(WarehouseSeeder::class);
        }

        return Material::create([
            'code' => 'MT-T'.fake()->unique()->numberBetween(1, 999999),
            'name' => 'HP 85A kartrij',
            'category_id' => MaterialCategory::where('code', 'cartridge')->value('id'),
            'unit_id' => Unit::where('code', 'pcs')->value('id'),
            ...$attributes,
        ]);
    }

    /** @return array{Warehouse, Material, Organization} */
    protected function stockFixture(): array
    {
        $organization = Organization::factory()->create();

        return [$this->warehouseIn($organization), $this->material(), $organization];
    }
}
