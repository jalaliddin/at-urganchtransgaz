<?php

namespace App\Services\Printers;

use App\Exceptions\DomainConflictException;
use App\Models\AssetCategory;
use App\Models\AssetModel;
use App\Models\CartridgeModel;
use App\Models\CartridgeReplacement;
use App\Models\MaterialCategory;
use App\Models\Unit;
use App\Services\Warehouse\MaterialCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cartridge models and their printer models (design P2, P3). A cartridge without a
 * chosen material gets one of its own in the warehouse catalog, so its stock,
 * receipts and issues are ordinary warehouse operations.
 */
class CartridgeCatalog
{
    public function __construct(private readonly MaterialCatalog $materials) {}

    /** @param  array<string, mixed>  $data  validated: name, part_number, kind, color, page_yield, is_active, material_id?, printer_model_ids? */
    public function save(?CartridgeModel $cartridge, array $data): CartridgeModel
    {
        return DB::transaction(function () use ($cartridge, $data): CartridgeModel {
            $modelIds = array_key_exists('printer_model_ids', $data) ? array_values(array_unique(array_map('intval', (array) $data['printer_model_ids']))) : null;
            unset($data['printer_model_ids']);

            if ($cartridge !== null) {
                $cartridge = CartridgeModel::query()->lockForUpdate()->findOrFail($cartridge->id);
                if (isset($data['material_id']) && (int) $data['material_id'] !== $cartridge->material_id && $this->used($cartridge)) {
                    throw ValidationException::withMessages(['material_id' => __('messages.cartridge_material_locked')]);
                }
                $cartridge->update(array_filter($data, fn ($v, $k) => $k !== 'material_id' || $v !== null, ARRAY_FILTER_USE_BOTH));
            } else {
                $data['material_id'] ??= $this->materials->create([
                    'name' => mb_substr($data['part_number'] ?? null ? "{$data['name']} ({$data['part_number']})" : (string) $data['name'], 0, 255),
                    'category_id' => MaterialCategory::query()->where('code', 'cartridge')->value('id'),
                    'unit_id' => Unit::query()->where('code', 'pcs')->value('id'),
                ])->id;
                $cartridge = CartridgeModel::create($data);
            }

            if ($modelIds !== null) {
                $cartridge->printerModels()->sync($modelIds);
            }

            return $cartridge;
        });
    }

    public function delete(CartridgeModel $cartridge): void
    {
        DB::transaction(function () use ($cartridge): void {
            $locked = CartridgeModel::query()->lockForUpdate()->findOrFail($cartridge->id);
            if ($this->used($locked)) {
                throw new DomainConflictException(__('messages.cartridge_in_use'));
            }
            $locked->delete();
        });
    }

    public function used(CartridgeModel $cartridge): bool
    {
        return CartridgeReplacement::query()->withoutGlobalScopes()->where('cartridge_model_id', $cartridge->id)->exists();
    }

    /**
     * Asset models of the printer category (the printer models of design P2).
     *
     * @return list<int>
     */
    public static function printerModelIds(): array
    {
        return AssetModel::query()->whereIn('category_id', AssetCategory::query()->select('id')->where('code', AssetCategory::PRINTER))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
