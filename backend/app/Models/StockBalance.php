<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A lot: quantity of one material (and batch) in one warehouse. Changed only by
 * StockService; the database refuses a negative quantity.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $material_id
 * @property string $batch
 * @property CarbonImmutable|null $expires_at
 * @property string $quantity
 * @property string $unit_cost
 * @property CarbonImmutable|null $received_at
 * @property-read Warehouse $warehouse
 * @property-read Material $material
 */
class StockBalance extends Model
{
    protected $fillable = ['warehouse_id', 'material_id', 'batch', 'expires_at', 'quantity', 'unit_cost', 'received_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_date',
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'received_at' => 'immutable_datetime',
        ];
    }

    /**
     * Lots in warehouses of $user's organizations.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $scope = $user->accessScope();
        if ($scope->isUnrestricted()) {
            return;
        }
        $ids = $scope->organizationIds();
        $ids === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('warehouse_id', DB::table('warehouses')->select('id')->whereIn('organization_id', $ids));
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
