<?php

namespace App\Models;

use App\Enums\ChangeCategory;
use App\Enums\ChangeRisk;
use App\Enums\ChangeStatus;
use App\Enums\ChangeType;
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
 * A planned network, server or configuration change (design C1).
 *
 * @property int $id
 * @property string $number
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property ChangeType $type
 * @property ChangeCategory $category
 * @property ChangeRisk $risk
 * @property string|null $impact
 * @property string|null $plan
 * @property string|null $backout_plan
 * @property CarbonImmutable|null $planned_start
 * @property CarbonImmutable|null $planned_end
 * @property CarbonImmutable|null $actual_start
 * @property CarbonImmutable|null $actual_end
 * @property ChangeStatus $status
 * @property int|null $implementer_id
 * @property int|null $problem_id
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property string|null $decision_comment
 * @property string|null $result
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Organization $organization
 * @property-read User|null $implementer
 * @property-read User|null $creator
 * @property-read User|null $decider
 * @property-read Problem|null $problem
 */
class Change extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps, ScopedToUserOrganizations;

    protected $fillable = [
        'organization_id', 'title', 'description', 'type', 'category', 'risk', 'impact', 'plan', 'backout_plan',
        'planned_start', 'planned_end', 'implementer_id', 'problem_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChangeType::class,
            'category' => ChangeCategory::class,
            'risk' => ChangeRisk::class,
            'status' => ChangeStatus::class,
            'planned_start' => 'immutable_datetime',
            'planned_end' => 'immutable_datetime',
            'actual_start' => 'immutable_datetime',
            'actual_end' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function implementer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'implementer_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withoutGlobalScopes();
    }

    /** @return BelongsTo<Problem, $this> */
    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class)->withoutGlobalScopes();
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'change_assets', 'change_id', 'asset_id')->withoutGlobalScopes();
    }

    /** @return HasMany<ChangeHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(ChangeHistory::class);
    }
}
