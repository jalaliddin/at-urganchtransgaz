<?php

namespace App\Listeners;

use App\Events\EmployeeDismissed;
use App\Models\User;
use App\Services\Auth\SessionRevoker;

/**
 * Off-boarding: a dismissed employee's system account is disabled immediately.
 */
class DeactivateLinkedUser
{
    public function __construct(private readonly SessionRevoker $sessions) {}

    public function handle(EmployeeDismissed $event): void
    {
        $user = User::withoutGlobalScopes()->where('employee_id', $event->employee->id)->first();
        if ($user === null) {
            return;
        }

        if ($user->is_active) {
            $user->update(['is_active' => false]);
        }
        $this->sessions->revokeAll($user);
    }
}
