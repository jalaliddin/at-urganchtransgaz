<?php

namespace App\Models;

use App\Enums\ReplacementSource;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A cartridge put into a printer (§8 cartridge_movements). organization_id is the
 * printer's organization at that time. Never deleted — cancelled (design P6).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property int $cartridge_model_id
 * @property CarbonImmutable $replaced_at
 * @property ReplacementSource $source
 * @property int|null $warehouse_id
 * @property int|null $stock_document_id
 * @property string|null $note
 * @property int|null $performed_by
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property int|null $return_document_id
 * @property CarbonImmutable $created_at
 * @property-read Asset $asset
 * @property-read CartridgeModel $cartridge
 * @property-read Warehouse|null $warehouse
 * @property-read StockDocument|null $stockDocument
 * @property-read StockDocument|null $returnDocument
 * @property-read User|null $performer
 * @property-read User|null $canceller
 * @property-read PrinterCounter|null $counter
 * @property-read Organization $organization
 */
class CartridgeReplacement extends Model
{
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'asset_id', 'cartridge_model_id', 'replaced_at', 'source', 'warehouse_id', 'stock_document_id',
        'note', 'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'replaced_at' => 'immutable_date',
            'source' => ReplacementSource::class,
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Replacements of the user's organizations (explicit; works in queued jobs).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $scope = $user->accessScope();
        $query->withoutGlobalScope(OrganizationScope::class)
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn($query->qualifyColumn('organization_id'), $scope->organizationIds() ?: [0]));
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('cancelled_at'));
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<CartridgeModel, $this> */
    public function cartridge(): BelongsTo
    {
        return $this->belongsTo(CartridgeModel::class, 'cartridge_model_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<StockDocument, $this> */
    public function stockDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<StockDocument, $this> */
    public function returnDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'return_document_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withoutGlobalScopes();
    }

    /** @return HasOne<PrinterCounter, $this> */
    public function counter(): HasOne
    {
        return $this->hasOne(PrinterCounter::class, 'replacement_id')->withoutGlobalScopes();
    }
}
