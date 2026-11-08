<?php

namespace App\Models;

use App\Enums\StockAlertType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Low-stock or expiry episode. open_key is unique while open, so each subject has
 * at most one open alert (and one notification) per episode.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $warehouse_id
 * @property int $material_id
 * @property StockAlertType $type
 * @property string $batch
 * @property CarbonImmutable|null $expires_at
 * @property string $quantity
 * @property string|null $open_key
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $resolved_at
 * @property-read Warehouse $warehouse
 * @property-read Material $material
 */
class StockAlert extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'warehouse_id', 'material_id', 'type', 'batch', 'expires_at', 'quantity', 'open_key', 'opened_at', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'type' => StockAlertType::class,
            'expires_at' => 'immutable_date',
            'quantity' => 'decimal:3',
            'opened_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
