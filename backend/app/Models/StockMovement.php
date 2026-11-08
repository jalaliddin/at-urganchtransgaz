<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal row: one lot change with the lot balance after it. organization_id is the
 * warehouse's organization.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $document_id
 * @property int|null $line_id
 * @property int $warehouse_id
 * @property int $material_id
 * @property int $balance_id
 * @property string $batch
 * @property string $quantity
 * @property string $unit_cost
 * @property string $balance_after
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read StockDocument $document
 * @property-read Warehouse $warehouse
 * @property-read Material $material
 */
class StockMovement extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'document_id', 'line_id', 'warehouse_id', 'material_id', 'balance_id', 'batch',
        'quantity', 'unit_cost', 'balance_after', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'balance_after' => 'decimal:3',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<StockDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'document_id');
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
