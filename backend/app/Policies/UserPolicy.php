<?php

namespace App\Policies;

use App\Models\User;

/**
 * Self-management and "only a Super Admin manages a Super Admin" are enforced again
 * in AccessManager, because Gate::before lets a Super Admin skip these methods.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view') && $user->canAccessOrganization($target->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $this->allowed($user, $target, 'users.update');
    }

    public function deactivate(User $user, User $target): bool
    {
        return $this->allowed($user, $target, 'users.deactivate') && ! $target->is($user);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->allowed($user, $target, 'users.reset_password') && ! $target->is($user);
    }

    public function manageAccess(User $user, User $target): bool
    {
        return $this->allowed($user, $target, 'users.manage_access') && ! $target->is($user);
    }

    private function allowed(User $user, User $target, string $permission): bool
    {
        return $user->can($permission)
            && $user->canAccessOrganization($target->organization_id)
            && (! $target->isSuperAdmin() || $user->isSuperAdmin());
    }
}
