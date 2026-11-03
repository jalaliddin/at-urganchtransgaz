<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('departments.view');
    }

    public function view(User $user, Department $model): bool
    {
        return $user->can('departments.view') && $user->canAccessOrganization($model->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->can('departments.create');
    }

    public function update(User $user, Department $model): bool
    {
        return $user->can('departments.update') && $user->canAccessOrganization($model->organization_id);
    }

    public function delete(User $user, Department $model): bool
    {
        return $user->can('departments.delete') && $user->canAccessOrganization($model->organization_id);
    }
}
