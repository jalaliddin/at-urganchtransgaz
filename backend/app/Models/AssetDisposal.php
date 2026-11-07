<?php

namespace App\Models;

use App\Enums\DisposalStatus;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Write-off request and its decision.
 *
 * @property int $id
 * @property int $asset_id
 * @property DisposalStatus $status
 * @property string $reason
 * @property string|null $act_number
 * @property int|null $requested_by
 * @property CarbonImmutable $requested_at
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_note
 * @property-read Asset $asset
 */
class AssetDisposal extends Model
{
    protected $fillable = ['asset_id', 'status', 'reason', 'act_number', 'requested_by', 'requested_at'];

    protected function casts(): array
    {
        return [
            'status' => DisposalStatus::class,
            'requested_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * Disposals of assets inside the current user's organization scope.
     *
     * @param  Builder<self>  $query
     */
    public function scopeInScope(Builder $query): void
    {
        $query->whereIn('asset_id', Asset::query()->select('id'));
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return static::query()->inScope()->where($field ?? 'id', $value)->firstOrFail();
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
