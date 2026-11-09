<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Explicit organization scope for models with `organization_id` (works in jobs and
 * commands too) and route binding that answers 404 outside the user's organizations.
 */
trait ScopedToUserOrganizations
{
    /** @param  Builder<static>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $scope = $user->accessScope();
        $query->withoutGlobalScope(OrganizationScope::class)
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn($query->qualifyColumn('organization_id'), $scope->organizationIds() ?: [0]));
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();
        $query = static::query();
        if ($user instanceof User) {
            $query->visibleTo($user);
        }

        return $query->where($field ?? $this->getRouteKeyName(), $value)->firstOrFail();
    }
}
