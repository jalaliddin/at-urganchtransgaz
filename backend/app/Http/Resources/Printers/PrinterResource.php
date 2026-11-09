<?php

namespace App\Http\Resources\Printers;

use App\Models\AssetModel;
use App\Models\CartridgeModel;

/** JSON shapes of the printer module (plain arrays, like the other module resources). */
final class PrinterResource
{
    /** @return array<string, mixed> */
    public static function cartridge(CartridgeModel $cartridge, ?string $stock = null, ?bool $canDelete = null): array
    {
        return [
            'id' => $cartridge->id,
            'name' => $cartridge->name,
            'part_number' => $cartridge->part_number,
            'kind' => $cartridge->kind->value,
            'color' => $cartridge->color->value,
            'page_yield' => $cartridge->page_yield,
            'is_active' => $cartridge->is_active,
            'material' => $cartridge->relationLoaded('material') ? [
                'id' => $cartridge->material->id,
                'code' => $cartridge->material->code,
                'name' => $cartridge->material->name,
                'unit' => $cartridge->material->relationLoaded('unit') ? $cartridge->material->unit->name : null,
            ] : null,
            'printer_models' => $cartridge->relationLoaded('printerModels')
                ? $cartridge->printerModels->sortBy('name')->map(fn (AssetModel $m) => self::printerModel($m))->values()->all() : [],
            'stock' => $stock,
            'can_delete' => $canDelete,
        ];
    }

    /** @return array{id: int, name: string, manufacturer: string|null} */
    public static function printerModel(AssetModel $model): array
    {
        return ['id' => $model->id, 'name' => $model->name, 'manufacturer' => $model->relationLoaded('manufacturer') ? $model->manufacturer->name : null];
    }
}
