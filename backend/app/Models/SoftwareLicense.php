<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Licence of a catalog title owned by an organization; the key is encrypted at rest.
 *
 * @property int $id
 * @property int $software_id
 * @property int $organization_id
 * @property string|null $number
 * @property string|null $license_key
 * @property int $seats
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $expires_at
 * @property string|null $supplier
 * @property string|null $cost
 * @property string|null $note
 * @property-read Software $software
 * @property-read Organization $organization
 */
class SoftwareLicense extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps;

    /** @var list<string> the key never reaches the audit log */
    protected array $auditExclude = ['license_key'];

    protected $hidden = ['license_key'];

    protected $fillable = ['software_id', 'organization_id', 'number', 'license_key', 'seats', 'valid_from', 'expires_at', 'supplier', 'cost', 'note'];

    protected function casts(): array
    {
        return [
            'license_key' => 'encrypted',
            'seats' => 'integer',
            'valid_from' => 'immutable_date',
            'expires_at' => 'immutable_date',
            'cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Software, $this> */
    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<SoftwareInstallation, $this> */
    public function installations(): HasMany
    {
        return $this->hasMany(SoftwareInstallation::class, 'license_id');
    }
}
