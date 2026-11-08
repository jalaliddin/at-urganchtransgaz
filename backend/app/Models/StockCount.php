<?php

namespace App\Models;

use App\Enums\StockCountStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock count of one warehouse: lots snapshotted at start, counted, closed into one
 * adjustment document. The warehouse is frozen while the count is open.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $warehouse_id
 * @property string $number
 * @property StockCountStatus $status
 * @property int|null $open_key
 * @property string|null $note
 * @property int|null $started_by
 * @property CarbonImmutable|null $started_at
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property int|null $adjustment_document_id
 * @property-read Warehouse $warehouse
 */
class StockCount extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'warehouse_id', 'number', 'status', 'open_key', 'note', 'started_by', 'started_at'];

    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'started_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<StockCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'count_id');
    }

    /** @return BelongsTo<StockDocument, $this> */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockDocument::class, 'adjustment_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
