<?php

namespace App\Models;

use App\Enums\SimStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Company SIM card held by an organization. The ICCID is encrypted at rest; a keyed hash keeps it unique and
 * searchable and its last four digits are shown instead of it.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $provider_id
 * @property string $phone_number 998XXXXXXXXX
 * @property string|null $phone_key
 * @property string|null $iccid
 * @property string|null $iccid_hash
 * @property string|null $iccid_last4
 * @property string|null $tariff
 * @property string|null $account_number
 * @property string|null $monthly_fee
 * @property int|null $department_id
 * @property int|null $employee_id
 * @property SimStatus $status
 * @property SimStatus|null $previous_status
 * @property CarbonImmutable|null $allocated_at
 * @property CarbonImmutable|null $returned_at
 * @property string|null $note
 * @property-read ServiceProvider $provider
 * @property-read Organization $organization
 * @property-read Department|null $department
 * @property-read Employee|null $employee
 * @property-read SimAssignment|null $openAssignment
 */
class SimCard extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps;

    /** @var list<string> the ICCID never reaches the audit log */
    protected array $auditExclude = ['iccid', 'iccid_hash', 'phone_key'];

    protected $hidden = ['iccid', 'iccid_hash'];

    protected $fillable = [
        'organization_id', 'provider_id', 'phone_number', 'tariff', 'account_number', 'monthly_fee',
        'department_id', 'employee_id', 'status', 'previous_status', 'allocated_at', 'returned_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'iccid' => 'encrypted',
            'monthly_fee' => 'decimal:2',
            'status' => SimStatus::class,
            'previous_status' => SimStatus::class,
            'allocated_at' => 'immutable_date',
            'returned_at' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SimCard $card): void {
            $card->phone_key = $card->status === SimStatus::Cancelled ? null : $card->phone_number;
        });
    }

    /** 998XXXXXXXXX from "+998 90 123-45-67", "90 123 45 67" …; null when it is not an Uzbek number. */
    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        return preg_match('/^998\d{9}$/', $digits) === 1 ? $digits : null;
    }

    /** Digits only; null when not 18–22 digits long (ITU-T E.118). */
    public static function normalizeIccid(?string $value): ?string
    {
        $digits = preg_replace('/[\s-]+/', '', (string) $value) ?? '';

        return preg_match('/^\d{18,22}$/', $digits) === 1 ? $digits : null;
    }

    public static function iccidHash(string $iccid): string
    {
        return hash_hmac('sha256', $iccid, (string) config('app.key'));
    }

    /** Sets the encrypted ICCID together with its hash and last four digits (null clears all three). */
    public function setIccid(?string $iccid): void
    {
        $this->iccid = $iccid;
        $this->iccid_hash = $iccid === null ? null : self::iccidHash($iccid);
        $this->iccid_last4 = $iccid === null ? null : substr($iccid, -4);
    }

    public function maskedIccid(): ?string
    {
        return $this->iccid_last4 === null ? null : str_repeat('•', 8).$this->iccid_last4;
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

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<SimAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(SimAssignment::class);
    }

    /** @return HasOne<SimAssignment, $this> */
    public function openAssignment(): HasOne
    {
        return $this->hasOne(SimAssignment::class)->whereNull('returned_at');
    }

    /** @return HasMany<SimHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(SimHistory::class);
    }
}
