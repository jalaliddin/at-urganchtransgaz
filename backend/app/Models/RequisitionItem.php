<?php

namespace App\Models;

use App\Enums\RequisitionItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $requisition_id
 * @property RequisitionItemKind $kind
 * @property int|null $material_id
 * @property int|null $asset_category_id
 * @property string $name
 * @property string $quantity
 * @property string|null $approved_quantity
 * @property string $fulfilled_quantity
 * @property int|null $unit_id
 * @property string|null $note
 * @property int $sort_order
 * @property-read Material|null $material
 * @property-read Unit|null $unit
 */
class RequisitionItem extends Model
{
    protected $fillable = ['requisition_id', 'kind', 'material_id', 'asset_category_id', 'name', 'quantity', 'approved_quantity', 'unit_id', 'note', 'sort_order'];

    protected function casts(): array
    {
        return [
            'kind' => RequisitionItemKind::class,
            'quantity' => 'decimal:3',
            'approved_quantity' => 'decimal:3',
            'fulfilled_quantity' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    /** Approved minus already allocated ("0.000" when nothing is left). */
    public function remaining(): string
    {
        $remaining = bcsub($this->approved_quantity ?? '0', $this->fulfilled_quantity, 3);

        return bccomp($remaining, '0', 3) > 0 ? $remaining : '0.000';
    }

    /** @return BelongsTo<Requisition, $this> */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<RequisitionFulfillment, $this> */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(RequisitionFulfillment::class, 'item_id');
    }
}
