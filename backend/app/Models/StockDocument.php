<?php

namespace App\Models;

use App\Enums\StockDocumentType;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A posted stock operation (receipt, issue, return, transfer, adjustment). Never
 * edited after posting; corrections are new documents.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property StockDocumentType $type
 * @property int $warehouse_id
 * @property int|null $target_warehouse_id
 * @property int|null $target_organization_id
 * @property CarbonImmutable $document_date
 * @property string|null $supplier
 * @property string|null $supplier_document
 * @property int|null $recipient_organization_id
 * @property int|null $recipient_employee_id
 * @property int|null $asset_id
 * @property string|null $reason
 * @property int|null $related_document_id
 * @property int|null $stock_count_id
 * @property int|null $requisition_id
 * @property int|null $file_id
 * @property string $total_amount
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read Warehouse $warehouse
 * @property-read Warehouse|null $targetWarehouse
 * @property-read Collection<int, StockDocumentLine> $lines
 */
class StockDocument extends Model
{
    protected $fillable = [
        'organization_id', 'number', 'type', 'warehouse_id', 'target_warehouse_id', 'target_organization_id',
        'document_date', 'supplier', 'supplier_document', 'recipient_organization_id', 'recipient_employee_id',
        'asset_id', 'reason', 'related_document_id', 'stock_count_id', 'requisition_id', 'file_id', 'total_amount',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockDocumentType::class,
            'document_date' => 'immutable_date',
            'total_amount' => 'decimal:2',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Documents whose source or target warehouse belongs to $user's organizations.
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
            : $query->where(fn ($q) => $q->whereIn('organization_id', $ids)->orWhereIn('target_organization_id', $ids));
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();
        $query = static::query();
        if ($user instanceof User) {
            $query->visibleTo($user);
        }

        return $query->where($field ?? 'id', $value)->firstOrFail();
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function recipientOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'recipient_organization_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function recipientEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recipient_employee_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<StockDocument, $this> */
    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'related_document_id');
    }

    /** @return BelongsTo<StoredFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Requisition, $this> */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<StockDocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class, 'document_id');
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'document_id')->withoutGlobalScope(OrganizationScope::class);
    }
}
