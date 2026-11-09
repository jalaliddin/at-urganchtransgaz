<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kanban board of an organization (scope). Archived boards are read-only.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_archived
 * @property-read Organization $organization
 */
class TaskBoard extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps;

    protected $fillable = ['organization_id', 'name', 'description', 'is_archived'];

    protected function casts(): array
    {
        return ['is_archived' => 'boolean'];
    }

    /**
     * Boards of the user's organizations (explicit, also without an authenticated request).
     *
     * @param  Builder<TaskBoard>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $scope = $user->accessScope();
        $query->withoutGlobalScope(OrganizationScope::class)
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn($query->qualifyColumn('organization_id'), $scope->organizationIds() ?: [0]));
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<TaskColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(TaskColumn::class, 'board_id')->orderBy('position');
    }

    /** @return HasMany<TaskLabel, $this> */
    public function labels(): HasMany
    {
        return $this->hasMany(TaskLabel::class, 'board_id')->orderBy('name');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'board_id');
    }
}
