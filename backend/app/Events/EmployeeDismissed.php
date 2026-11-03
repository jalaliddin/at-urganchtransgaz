<?php

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;

/** The employee's status became "dismissed". */
class EmployeeDismissed
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee) {}
}
