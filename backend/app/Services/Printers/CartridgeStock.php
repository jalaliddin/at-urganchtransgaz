<?php

namespace App\Services\Printers;

use App\Models\StockBalance;
use App\Models\User;
use App\Support\Decimal;

/** Cartridge stock = the stock of its material in the warehouses the user can see. */
class CartridgeStock
{
    /**
     * @param  list<int>  $materialIds
     * @return array<int, string> material id => quantity (scale 3)
     */
    public function onHand(User $user, array $materialIds): array
    {
        if ($materialIds === []) {
            return [];
        }

        return StockBalance::query()->visibleTo($user)->whereIn('material_id', array_values(array_unique($materialIds)))
            ->groupBy('material_id')->selectRaw('material_id, SUM(quantity) AS qty')->pluck('qty', 'material_id')
            ->mapWithKeys(fn ($qty, $id) => [(int) $id => Decimal::of((string) $qty)])->all();
    }

    /**
     * Per-warehouse stock of one material, for the replacement picker.
     *
     * @return list<array{warehouse_id: int, quantity: string}>
     */
    public function byWarehouse(User $user, int $materialId): array
    {
        return StockBalance::query()->visibleTo($user)->where('material_id', $materialId)->where('quantity', '>', 0)
            ->groupBy('warehouse_id')->selectRaw('warehouse_id, SUM(quantity) AS qty')->get()
            ->map(fn ($row) => ['warehouse_id' => (int) $row->getAttribute('warehouse_id'), 'quantity' => Decimal::of((string) $row->getAttribute('qty'))])
            ->values()->all();
    }
}
