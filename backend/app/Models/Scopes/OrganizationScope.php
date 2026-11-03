<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Support\Access\OrganizationAccessResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts organization-owned models to the authenticated user's access scope.
 * Only active once a user is resolved (Auth::hasUser()), so authentication itself,
 * console commands and queued jobs are unaffected.
 *
 * @implements Scope<Model>
 */
class OrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::hasUser()) {
            return;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $scope = app(OrganizationAccessResolver::class)->resolve($user);
        if ($scope->isUnrestricted()) {
            return;
        }

        /** @var callable(): string $column */
        $column = [$model, 'organizationColumn'];
        $qualified = $model->qualifyColumn($column());
        $ids = $scope->organizationIds();

        if ($ids === []) {
            $builder->whereRaw('1 = 0');
        } else {
            $builder->whereIn($qualified, $ids);
        }
    }
}
