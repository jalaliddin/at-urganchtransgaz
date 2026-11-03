<?php

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

/** The employee moved from $fromOrganizationId to $employee->organization_id. */
class EmployeeTransferred
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee, public readonly int $fromOrganizationId) {}
}
