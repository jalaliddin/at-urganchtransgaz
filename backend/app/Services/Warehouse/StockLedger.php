<?php

namespace App\Services\Warehouse;

use App\Exceptions\InsufficientStockException;
use App\Models\Material;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Lot-level changes. Callers hold the warehouse row lock (StockService), so the
 * read-check-write below cannot interleave; lots are still read FOR UPDATE and the
 * database CHECK refuses a negative quantity as the last line of defence.
 */
class StockLedger
{
    /**
     * Adds to a lot (created on first use), averaging its unit cost, and journals it.
     * $unitCost null keeps the lot's cost (returns/adjustments without a price).
     */
    public function increase(
        StockDocument $document, ?StockDocumentLine $line, int $warehouseId, int $organizationId, Material $material,
        string $batch, ?string $expiresAt, string $quantity, ?string $unitCost, User $by, string $errorKey,
    ): StockMovement {
        $lot = StockBalance::query()->where('warehouse_id', $warehouseId)->where('material_id', $material->id)
            ->where('batch', $batch)->lockForUpdate()->first();
        $now = CarbonImmutable::now();

        if ($lot === null) {
            if ($material->track_expiry && $expiresAt === null) {
                throw ValidationException::withMessages([$errorKey.'.expires_at' => __('messages.expiry_required')]);
            }
            $lot = StockBalance::create([
                'warehouse_id' => $warehouseId, 'material_id' => $material->id, 'batch' => $batch, 'expires_at' => $expiresAt,
                'quantity' => '0', 'unit_cost' => $unitCost ?? '0', 'received_at' => $now,
            ]);
        }

        $empty = Decimal::cmp($lot->quantity, '0') === 0;
        if ($expiresAt !== null && $lot->expires_at?->toDateString() !== $expiresAt) {
            if (! $empty) {
                throw ValidationException::withMessages([$errorKey.'.expires_at' => __('messages.batch_expiry_mismatch', [
                    'batch' => $batch, 'date' => $lot->expires_at?->format('d.m.Y') ?? '—',
                ])]);
            }
            $lot->expires_at = CarbonImmutable::parse($expiresAt);
        }

        $newQuantity = Decimal::add($lot->quantity, $quantity);
        if ($unitCost !== null) {
            $value = bcadd(bcmul($lot->quantity, $lot->unit_cost, 6), bcmul($quantity, $unitCost, 6), 6);
            $lot->unit_cost = Decimal::div($value, $newQuantity, 2);
        }
        if ($empty) {
            $lot->received_at = $now;
        }
        $lot->quantity = $newQuantity;
        $lot->save();

        return $this->journal($document, $line, $lot, $organizationId, $quantity, $unitCost ?? $lot->unit_cost, $by);
    }

    /** Takes $quantity from a locked lot that holds at least that much. */
    public function decrease(StockDocument $document, ?StockDocumentLine $line, StockBalance $lot, int $organizationId, string $quantity, User $by): StockMovement
    {
        $lot->quantity = Decimal::sub($lot->quantity, $quantity);
        $lot->save();

        return $this->journal($document, $line, $lot, $organizationId, Decimal::neg($quantity), $lot->unit_cost, $by);
    }

    /**
     * Picks lots for a decrease: the named batch, or FIFO — earliest expiry first
     * (lots without expiry last), then the oldest receipt.
     *
     * @return list<array{0: StockBalance, 1: string}>
     */
    public function allocate(int $warehouseId, Material $material, ?string $batch, string $quantity): array
    {
        $lots = StockBalance::query()->where('warehouse_id', $warehouseId)->where('material_id', $material->id)
            ->when($batch !== null, fn ($q) => $q->where('batch', $batch))
            ->where('quantity', '>', 0)
            ->orderByRaw('expires_at IS NULL')->orderBy('expires_at')->orderBy('received_at')->orderBy('id')
            ->lockForUpdate()->get();

        $picks = [];
        $remaining = $quantity;
        $available = '0.000';
        foreach ($lots as $lot) {
            $available = Decimal::add($available, $lot->quantity);
            if (Decimal::cmp($remaining, '0') > 0) {
                $take = Decimal::min($lot->quantity, $remaining);
                $picks[] = [$lot, $take];
                $remaining = Decimal::sub($remaining, $take);
            }
        }

        if (Decimal::cmp($remaining, '0') > 0) {
            throw new InsufficientStockException([[
                'material_id' => $material->id, 'material' => $material->name, 'batch' => $batch === '' ? null : $batch,
                'available' => $available, 'requested' => $quantity,
            ]]);
        }

        return $picks;
    }

    private function journal(StockDocument $document, ?StockDocumentLine $line, StockBalance $lot, int $organizationId, string $quantity, string $unitCost, User $by): StockMovement
    {
        return StockMovement::query()->withoutGlobalScopes()->create([
            'organization_id' => $organizationId, 'document_id' => $document->id, 'line_id' => $line?->id,
            'warehouse_id' => $lot->warehouse_id, 'material_id' => $lot->material_id, 'balance_id' => $lot->id,
            'batch' => $lot->batch, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'balance_after' => $lot->quantity,
            'created_by' => $by->id,
        ]);
    }
}
