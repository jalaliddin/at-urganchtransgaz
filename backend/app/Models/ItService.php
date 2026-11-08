<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ServiceType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Internet, telephony or other IT service an organization pays for. `fee` is the amount per billing period.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $provider_id
 * @property int|null $contract_id
 * @property ServiceType $type
 * @property string $name
 * @property string|null $address
 * @property string|null $identifier
 * @property string|null $tariff
 * @property string $fee
 * @property BillingCycle $billing_cycle
 * @property int $due_day
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 * @property bool $is_active
 * @property string|null $note
 * @property-read ServiceProvider $provider
 * @property-read Organization $organization
 * @property-read ServiceContract|null $contract
 */
class ItService extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps;

    protected $fillable = [
        'organization_id', 'provider_id', 'contract_id', 'type', 'name', 'address', 'identifier', 'tariff', 'fee',
        'billing_cycle', 'due_day', 'started_at', 'ended_at', 'is_active', 'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'billing_cycle' => BillingCycle::class,
            'fee' => 'decimal:2',
            'due_day' => 'integer',
            'started_at' => 'immutable_date',
            'ended_at' => 'immutable_date',
            'is_active' => 'boolean',
        ];
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

    /** @return BelongsTo<ServiceContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(ServiceContract::class, 'contract_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<ServiceInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(ServiceInvoice::class, 'service_id');
    }
}
