<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    public function view(User $user, Employee $model): bool
    {
        return $user->can('employees.view') && $user->canAccessOrganization($model->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    public function update(User $user, Employee $model): bool
    {
        return $user->can('employees.update') && $user->canAccessOrganization($model->organization_id);
    }

    public function delete(User $user, Employee $model): bool
    {
        return $user->can('employees.delete') && $user->canAccessOrganization($model->organization_id);
    }
}
