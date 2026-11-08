<?php

namespace App\Models;

use App\Enums\InstallationSource;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Software on a device: a catalog title, or an unrecognised name reported by an agent/CSV.
 *
 * @property int $id
 * @property int $asset_id
 * @property int|null $software_id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $vendor
 * @property string|null $version
 * @property InstallationSource $source
 * @property int|null $license_id
 * @property CarbonImmutable|null $installed_at
 * @property CarbonImmutable|null $last_seen_at
 * @property-read Asset $asset
 * @property-read Software|null $software
 * @property-read SoftwareLicense|null $license
 */
class SoftwareInstallation extends Model
{
    protected $fillable = ['asset_id', 'software_id', 'name', 'normalized_name', 'vendor', 'version', 'source', 'license_id', 'installed_at', 'last_seen_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'source' => InstallationSource::class,
            'installed_at' => 'immutable_date',
            'last_seen_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Installations on devices in $user's organizations.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($query->qualifyColumn('asset_id'), Asset::query()->visibleTo($user)->select('assets.id'));
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

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Software, $this> */
    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    /** @return BelongsTo<SoftwareLicense, $this> */
    public function license(): BelongsTo
    {
        return $this->belongsTo(SoftwareLicense::class, 'license_id')->withoutGlobalScope(OrganizationScope::class);
    }
}
