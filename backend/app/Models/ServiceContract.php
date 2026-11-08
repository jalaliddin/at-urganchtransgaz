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
 * Contract of an organization with a provider.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $provider_id
 * @property string $number
 * @property string|null $subject
 * @property CarbonImmutable|null $signed_at
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property CarbonImmutable|null $terminated_at
 * @property string|null $amount
 * @property string|null $note
 * @property-read ServiceProvider $provider
 * @property-read Organization $organization
 */
class ServiceContract extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps;

    protected $fillable = ['organization_id', 'provider_id', 'number', 'subject', 'signed_at', 'valid_from', 'valid_to', 'terminated_at', 'amount', 'note'];

    protected function casts(): array
    {
        return [
            'signed_at' => 'immutable_date',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'terminated_at' => 'immutable_date',
            'amount' => 'decimal:2',
        ];
    }

    /** terminated (termination date reached) → expired (past its end date) → active. */
    public function statusOn(?CarbonImmutable $today = null): string
    {
        $today = ($today ?? CarbonImmutable::today((string) config('app.display_timezone')))->startOfDay();
        if ($this->terminated_at !== null && $this->terminated_at->lte($today)) {
            return 'terminated';
        }

        return $this->valid_to !== null && $this->valid_to->lt($today) ? 'expired' : 'active';
    }

    /** @return BelongsTo<ServiceProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<ItService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(ItService::class, 'contract_id');
    }
}
