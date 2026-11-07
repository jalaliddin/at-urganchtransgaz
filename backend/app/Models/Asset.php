<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An IT asset (CMDB item). Its history lives in asset_events.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $department_id
 * @property string $inventory_number
 * @property string $name
 * @property int $category_id
 * @property int|null $manufacturer_id
 * @property int|null $model_id
 * @property string|null $serial_number
 * @property string|null $hostname
 * @property string|null $ip_address
 * @property string|null $mac_address
 * @property string|null $vlan
 * @property string|null $subnet
 * @property AssetStatus $status
 * @property AssetStatus|null $previous_status
 * @property int|null $holder_employee_id
 * @property int|null $responsible_employee_id
 * @property int|null $location_id
 * @property string|null $room
 * @property CarbonImmutable|null $purchase_date
 * @property CarbonImmutable|null $warranty_until
 * @property CarbonImmutable|null $warranty_notified_for
 * @property string|null $cost
 * @property string|null $supplier
 * @property string|null $specs
 * @property string|null $notes
 * @property int|null $transfer_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class Asset extends Model
{
    use BelongsToOrganization, HasUserStamps;

    protected $fillable = [
        'organization_id', 'department_id', 'inventory_number', 'name', 'category_id', 'manufacturer_id', 'model_id',
        'serial_number', 'hostname', 'ip_address', 'mac_address', 'vlan', 'subnet', 'status',
        'responsible_employee_id', 'location_id', 'room', 'purchase_date', 'warranty_until', 'cost', 'supplier',
        'specs', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'previous_status' => AssetStatus::class,
            'purchase_date' => 'immutable_date',
            'warranty_until' => 'immutable_date',
            'warranty_notified_for' => 'immutable_date',
            'cost' => 'decimal:2',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Assets in $user's organization scope, applied explicitly (works in queued jobs
     * where the global OrganizationScope is inactive).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->withoutGlobalScope(OrganizationScope::class);
        $scope = $user->accessScope();
        if (! $scope->isUnrestricted()) {
            $ids = $scope->organizationIds();
            $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn($query->qualifyColumn('organization_id'), $ids);
        }
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

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /** @return BelongsTo<Manufacturer, $this> */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /** @return BelongsTo<AssetModel, $this> */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'model_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'holder_employee_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<Employee, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<AssetEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    /** @return HasMany<AssetAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }
}
