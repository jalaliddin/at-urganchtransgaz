<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One allocation against a requisition item: a stock document, an asset (with the
 * asset transfer when it came from another organization) or a note.
 *
 * @property int $id
 * @property int $requisition_id
 * @property int $item_id
 * @property FulfillmentType $type
 * @property string $quantity
 * @property int|null $stock_document_id
 * @property int|null $asset_id
 * @property int|null $asset_transfer_id
 * @property int|null $sim_card_id
 * @property string|null $note
 * @property int|null $fulfilled_by
 * @property CarbonImmutable $created_at
 */
class RequisitionFulfillment extends Model
{
    protected $fillable = ['requisition_id', 'item_id', 'type', 'quantity', 'stock_document_id', 'asset_id', 'asset_transfer_id', 'sim_card_id', 'note', 'fulfilled_by'];

    protected function casts(): array
    {
        return [
            'type' => FulfillmentType::class,
            'quantity' => 'decimal:3',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<RequisitionItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class, 'item_id');
    }

    /** @return BelongsTo<StockDocument, $this> */
    public function stockDocument(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<AssetTransfer, $this> */
    public function assetTransfer(): BelongsTo
    {
        return $this->belongsTo(AssetTransfer::class);
    }

    /** @return BelongsTo<SimCard, $this> */
    public function simCard(): BelongsTo
    {
        return $this->belongsTo(SimCard::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
