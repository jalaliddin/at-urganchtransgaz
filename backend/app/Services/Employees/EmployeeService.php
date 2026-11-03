<?php

namespace App\Services\Employees;

use App\Enums\EmployeeStatus;
use App\Events\EmployeeDismissed;
use App\Events\EmployeeTransferred;
use App\Exceptions\DomainConflictException;
use App\Models\Employee;
use App\Models\OrganizationItResponsible;
use App\Models\User;
use App\Services\Users\AccessManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(private readonly AccessManager $access) {}

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
    public function update(Employee $employee, array $data, ?User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor): Employee {
            $fromOrganizationId = $employee->organization_id;
            $wasDismissed = $employee->status === EmployeeStatus::Dismissed;

            $employee->fill($data);
            $transferred = $employee->organization_id !== $fromOrganizationId;
            $dismissing = ! $wasDismissed && $employee->status === EmployeeStatus::Dismissed;
            if ($transferred || $dismissing) {
                $this->guardLinkedAccount($employee, $actor, $dismissing);
            }
            if ($transferred && ! array_key_exists('department_id', $data)) {
                $employee->department_id = null;
            }
            $employee->save();

            if ($transferred) {
                EmployeeTransferred::dispatch($employee, $fromOrganizationId);
            }
            if ($dismissing) {
                EmployeeDismissed::dispatch($employee);
            }

            return $employee->refresh();
        });
    }

    /**
     * Dismissal deactivates and transfer re-scopes the linked account, so both need the
     * same authority as managing that account directly.
     *
     * @throws AuthorizationException|DomainConflictException
     */
    private function guardLinkedAccount(Employee $employee, ?User $actor, bool $dismissing): void
    {
        $user = User::withoutGlobalScopes()->whereNull('deleted_at')->where('employee_id', $employee->id)->first();
        if ($user === null) {
            return;
        }

        $this->access->assertCanAdminister($user, $actor);
        if ($dismissing && $user->is_active && $user->isSuperAdmin()) {
            $this->access->ensureAnotherActiveSuperAdmin($user);
        }
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
