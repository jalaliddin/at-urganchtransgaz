<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Building / floor / room of an organization where assets are used.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 */
class Location extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps;

    protected $fillable = ['organization_id', 'parent_id', 'name', 'code', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<Location, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** "Bosh bino / 214-xona" */
    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' / '.$this->name : $this->name;
    }
}
