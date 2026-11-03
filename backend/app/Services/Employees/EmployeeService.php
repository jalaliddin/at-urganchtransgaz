<?php

namespace App\Services\Employees;

use App\Enums\EmployeeStatus;
use App\Events\EmployeeDismissed;
use App\Events\EmployeeTransferred;
use App\Exceptions\DomainConflictException;
use App\Models\Employee;
use App\Models\OrganizationItResponsible;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    /** @param array<string, mixed> $data validated */
    public function create(array $data): Employee
    {
        return DB::transaction(fn (): Employee => Employee::create($data)->refresh());
    }

    /**
     * @param  array<string, mixed>  $data  validated
     *
     * Side effects: EmployeeTransferred when the organization changes (the department
     * is cleared unless a new one is given), EmployeeDismissed when status becomes dismissed.
     */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $fromOrganizationId = $employee->organization_id;
            $wasDismissed = $employee->status === EmployeeStatus::Dismissed;

            $employee->fill($data);
            $transferred = $employee->organization_id !== $fromOrganizationId;
            if ($transferred && ! array_key_exists('department_id', $data)) {
                $employee->department_id = null;
            }
            $employee->save();

            if ($transferred) {
                EmployeeTransferred::dispatch($employee, $fromOrganizationId);
            }
            if (! $wasDismissed && $employee->status === EmployeeStatus::Dismissed) {
                EmployeeDismissed::dispatch($employee);
            }

            return $employee->refresh();
        });
    }

    /** @throws DomainConflictException when a system user is still linked */
    public function delete(Employee $employee): void
    {
        if (User::withoutGlobalScopes()->withTrashed()->where('employee_id', $employee->id)->exists()) {
            throw new DomainConflictException(__('messages.employee_has_user'));
        }

        DB::transaction(function () use ($employee): void {
            OrganizationItResponsible::withoutGlobalScopes()->where('employee_id', $employee->id)->get()
                ->each(fn (OrganizationItResponsible $row) => $row->delete());
            $employee->delete();
        });
    }
}
