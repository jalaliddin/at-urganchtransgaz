<?php

namespace App\Models;

use App\Enums\TransferStatus;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hand-over of assets from one organization to another (with an act).
 *
 * @property int $id
 * @property string $number
 * @property int $from_organization_id
 * @property int $to_organization_id
 * @property TransferStatus $status
 * @property string|null $note
 * @property int|null $act_file_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 * @property-read Organization $fromOrganization
 * @property-read Organization $toOrganization
 * @property-read StoredFile|null $act
 */
class AssetTransfer extends Model
{
    protected $fillable = ['number', 'from_organization_id', 'to_organization_id', 'status', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'sent_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Transfers whose sending or receiving organization is in $user's scope.
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
            : $query->where(fn ($q) => $q->whereIn('from_organization_id', $ids)->orWhereIn('to_organization_id', $ids));
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
    public function fromOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'from_organization_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function toOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'to_organization_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<AssetTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AssetTransferItem::class, 'transfer_id');
    }

    /** @return BelongsTo<StoredFile, $this> */
    public function act(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'act_file_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScope(OrganizationScope::class);
    }
}
