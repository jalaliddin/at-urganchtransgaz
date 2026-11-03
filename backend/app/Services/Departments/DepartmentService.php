<?php

namespace App\Services\Departments;

use App\Exceptions\DomainConflictException;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    /** @param array<string, mixed> $data validated */
    public function create(array $data): Department
    {
        return DB::transaction(fn (): Department => Department::create($data)->refresh());
    }

    /** @param array<string, mixed> $data validated (organization_id is immutable) */
    public function update(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data): Department {
            $department->update($data);

            return $department->refresh();
        });
    }

    /** @throws DomainConflictException when employees or sub-departments remain */
    public function delete(Department $department): void
    {
        $busy = Employee::withoutGlobalScopes()->whereNull('deleted_at')->where('department_id', $department->id)->exists()
            || Department::withoutGlobalScopes()->whereNull('deleted_at')->where('parent_id', $department->id)->exists();

        if ($busy) {
            throw new DomainConflictException(__('messages.department_not_empty'));
        }

        DB::transaction(fn () => $department->delete());
    }

    /** True when $candidateParentId is $department itself or one of its descendants. */
    public function wouldCreateCycle(Department $department, int $candidateParentId): bool
    {
        $current = $candidateParentId;
        for ($guard = 0; $guard < 50 && $current !== null; $guard++) {
            if ($current === $department->id) {
                return true;
            }
            $parent = DB::table('departments')->where('id', $current)->value('parent_id');
            $current = $parent === null ? null : (int) $parent;
        }

        return false;
    }
}
