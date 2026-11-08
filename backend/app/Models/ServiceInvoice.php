<?php

namespace App\Models;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a service costs for one billing period (regular) or a one-off additional cost (extra), with the invoice
 * received, central approval and payments. `status` is derived (InvoiceStatus::derive) and stored for filtering.
 *
 * @property int $id
 * @property int $service_id
 * @property int $organization_id
 * @property InvoiceKind $kind
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property string|null $period_key
 * @property string $planned_amount
 * @property string|null $invoice_number
 * @property CarbonImmutable|null $invoice_date
 * @property string|null $amount
 * @property CarbonImmutable $due_date
 * @property string $paid_amount
 * @property InvoiceStatus $status
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|null $file_id
 * @property string|null $description
 * @property string|null $note
 * @property-read ItService $service
 * @property-read Organization $organization
 * @property-read User|null $approver
 * @property-read StoredFile|null $file
 */
class ServiceInvoice extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps;

    protected $fillable = [
        'service_id', 'organization_id', 'kind', 'period_start', 'period_end', 'period_key', 'planned_amount',
        'invoice_number', 'invoice_date', 'amount', 'due_date', 'paid_amount', 'status', 'approved_by', 'approved_at',
        'file_id', 'description', 'note',
    ];

    protected function casts(): array
    {
        return [
            'kind' => InvoiceKind::class,
            'status' => InvoiceStatus::class,
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'invoice_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
            'planned_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ItService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ItService::class, 'service_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<StoredFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    /** @return HasMany<ServicePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ServicePayment::class, 'invoice_id');
    }
}
