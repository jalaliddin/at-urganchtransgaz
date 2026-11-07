<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('assets.view') && $user->canAccessOrganization($asset->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->can('assets.create');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->can('assets.update') && $user->canAccessOrganization($asset->organization_id);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('assets.delete') && $user->canAccessOrganization($asset->organization_id);
    }

    public function assign(User $user, Asset $asset): bool
    {
        return $user->can('assets.assign') && $user->canAccessOrganization($asset->organization_id);
    }
}
