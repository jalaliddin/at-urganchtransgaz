<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('organizations.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->can('organizations.view') && $user->canAccessOrganization($organization->id);
    }

    public function create(User $user): bool
    {
        return $user->can('organizations.create');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can('organizations.update') && $user->canAccessOrganization($organization->id);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->can('organizations.delete') && $user->canAccessOrganization($organization->id);
    }

    public function manageItStaff(User $user, Organization $organization): bool
    {
        return $user->can('organizations.manage_it_staff') && $user->canAccessOrganization($organization->id);
    }
}
