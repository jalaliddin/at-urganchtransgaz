<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $count_id
 * @property int $material_id
 * @property string $batch
 * @property CarbonImmutable|null $expires_at
 * @property string $expected_quantity
 * @property string|null $counted_quantity
 * @property string $unit_cost
 * @property string|null $note
 * @property int|null $counted_by
 * @property CarbonImmutable|null $counted_at
 * @property-read Material $material
 */
class StockCountLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['count_id', 'material_id', 'batch', 'expires_at', 'expected_quantity', 'counted_quantity', 'unit_cost', 'note', 'counted_by', 'counted_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_date',
            'expected_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'counted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
