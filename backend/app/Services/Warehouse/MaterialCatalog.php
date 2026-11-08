<?php

namespace App\Services\Warehouse;

use App\Models\Material;
use App\Models\StockBalance;
use App\Services\Support\NumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Materials: generated codes and tracking rules that keep existing lots valid. */
class MaterialCatalog
{
    public function __construct(private readonly NumberSequence $numbers) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Material
    {
        $this->checkTracking($data, null);

        return DB::transaction(function () use ($data): Material {
            if (empty($data['code'])) {
                $data['code'] = $this->nextCode();
            }

            return Material::create($data);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Material $material, array $data): Material
    {
        $this->checkTracking($data, $material);
        $material->update($data);

        return $material;
    }

    private function nextCode(): string
    {
        do {
            $code = sprintf('MT-%05d', $this->numbers->next('material'));
        } while (Material::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Expiry is tracked per batch, so it needs batches; the flags cannot change once
     * lots exist (their batch keys were built under the old rule).
     *
     * @param  array<string, mixed>  $data
     */
    private function checkTracking(array $data, ?Material $material): void
    {
        $batches = (bool) ($data['track_batches'] ?? $material->track_batches ?? false);
        $expiry = (bool) ($data['track_expiry'] ?? $material->track_expiry ?? false);
        if ($expiry && ! $batches) {
            throw ValidationException::withMessages(['track_batches' => __('messages.expiry_needs_batches')]);
        }
        if ($material === null) {
            return;
        }
        $changed = $batches !== $material->track_batches || $expiry !== $material->track_expiry;
        if ($changed && StockBalance::query()->where('material_id', $material->id)->exists()) {
            throw ValidationException::withMessages(['track_batches' => __('messages.material_tracking_locked')]);
        }
    }
}
