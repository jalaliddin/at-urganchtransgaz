<?php

namespace App\Models;

use App\Enums\CounterSource;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A page counter reading (design P7). organization_id is the printer's
 * organization when it was read.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property CarbonImmutable $read_at
 * @property int $total
 * @property bool $is_reset
 * @property CounterSource $source
 * @property int|null $replacement_id
 * @property string|null $note
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read User|null $creator
 */
class PrinterCounter extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'asset_id', 'read_at', 'total', 'is_reset', 'source', 'replacement_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_date',
            'total' => 'integer',
            'is_reset' => 'boolean',
            'source' => CounterSource::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $scope = $user->accessScope();
        $query->withoutGlobalScope(OrganizationScope::class)
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn($query->qualifyColumn('organization_id'), $scope->organizationIds() ?: [0]));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }
}
