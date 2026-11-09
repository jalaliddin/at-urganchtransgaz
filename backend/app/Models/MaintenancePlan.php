<?php

namespace App\Models;

use App\Enums\MaintenanceUnit;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Concerns\ScopedToUserOrganizations;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Preventive maintenance schedule for a set of devices (design M1).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property list<string>|null $checklist
 * @property int $interval_count
 * @property MaintenanceUnit $interval_unit
 * @property CarbonImmutable $start_on
 * @property int|null $assignee_id
 * @property int $lead_days
 * @property bool $is_active
 * @property-read Organization $organization
 * @property-read User|null $assignee
 */
class MaintenancePlan extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps, ScopedToUserOrganizations;

    protected $fillable = ['organization_id', 'title', 'description', 'checklist', 'interval_count', 'interval_unit', 'start_on', 'assignee_id', 'lead_days', 'is_active'];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'interval_count' => 'integer',
            'interval_unit' => MaintenanceUnit::class,
            'start_on' => 'immutable_date',
            'lead_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withoutGlobalScopes();
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'maintenance_plan_assets', 'plan_id', 'asset_id')->withoutGlobalScopes();
    }

    /** @return HasMany<MaintenanceTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class, 'plan_id');
    }
}
