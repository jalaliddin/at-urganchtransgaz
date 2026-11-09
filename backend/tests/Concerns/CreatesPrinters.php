<?php

namespace Tests\Concerns;

use App\Enums\StockDocumentType;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CartridgeModel;
use App\Models\Manufacturer;
use App\Models\Organization;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockService;
use Database\Seeders\AssetsSeeder;
use Database\Seeders\WarehouseSeeder;

/** Printer and cartridge fixtures. Requires InteractsWithAccess. */
trait CreatesPrinters
{
    use CreatesAssets, CreatesWarehouse;

    protected function seedPrinters(): void
    {
        $this->seedRbac();
        $this->seed(AssetsSeeder::class);
        $this->seed(WarehouseSeeder::class);
    }

    protected function printerModel(string $name = 'LaserJet Pro M404'): AssetModel
    {
        $manufacturer = Manufacturer::query()->firstOrCreate(['name' => 'HP']);

        return AssetModel::create([
            'manufacturer_id' => $manufacturer->id,
            'category_id' => $this->assetCategory('printer')->id,
            'name' => $name.' '.fake()->unique()->numberBetween(1, 99999),
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function printerIn(Organization $organization, User $by, array $attributes = []): Asset
    {
        return $this->assetIn($organization, $by, [
            'name' => 'HP LaserJet Pro M404',
            'category_id' => $this->assetCategory('printer')->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<AssetModel>  $fits
     */
    protected function cartridge(array $attributes = [], array $fits = []): CartridgeModel
    {
        $cartridge = CartridgeModel::create([
            'name' => 'HP 59A',
            'kind' => 'toner',
            'color' => 'black',
            'page_yield' => 3000,
            ...$attributes,
            'material_id' => $attributes['material_id'] ?? $this->material(['name' => 'HP 59A toner'])->id,
        ]);
        $cartridge->printerModels()->sync(array_map(fn (AssetModel $m) => $m->id, $fits));

        return $cartridge;
    }

    protected function stockUp(Warehouse $warehouse, CartridgeModel $cartridge, int $quantity, User $by, string $unitCost = '250000'): StockDocument
    {
        return app(StockService::class)->post(StockDocumentType::Receipt, ['warehouse_id' => $warehouse->id], [
            ['material_id' => $cartridge->material_id, 'quantity' => $quantity, 'unit_cost' => $unitCost],
        ], $by);
    }
}
