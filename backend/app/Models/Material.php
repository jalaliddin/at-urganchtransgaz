<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog entry for a consumable or part kept in warehouses.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $category_id
 * @property int $unit_id
 * @property bool $track_batches
 * @property bool $track_expiry
 * @property string $min_quantity
 * @property string|null $description
 * @property bool $is_active
 * @property-read MaterialCategory $category
 * @property-read Unit $unit
 */
class Material extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['code', 'name', 'category_id', 'unit_id', 'track_batches', 'track_expiry', 'min_quantity', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'track_batches' => 'boolean',
            'track_expiry' => 'boolean',
            'min_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<MaterialCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<StockBalance, $this> */
    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }
}
