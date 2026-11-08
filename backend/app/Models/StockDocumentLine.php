<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property int $material_id
 * @property string|null $batch
 * @property CarbonImmutable|null $expires_at
 * @property string $quantity
 * @property string|null $unit_cost
 * @property string $amount
 * @property string|null $note
 * @property-read Material $material
 */
class StockDocumentLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['document_id', 'material_id', 'batch', 'expires_at', 'quantity', 'unit_cost', 'amount', 'note'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_date',
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
