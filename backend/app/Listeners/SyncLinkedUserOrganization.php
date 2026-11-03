<?php

namespace App\Listeners;

use App\Events\EmployeeTransferred;
use App\Models\User;
use App\Services\Users\AccessManager;
use Illuminate\Support\Facades\Auth;

/**
 * A transferred employee's account follows them (see AccessManager::moveHomeOrganization).
 * EmployeeService has already checked that the actor may administer this account.
 */
class SyncLinkedUserOrganization
{
    public function __construct(private readonly AccessManager $access) {}

    public function handle(EmployeeTransferred $event): void
    {
        $user = User::withoutGlobalScopes()->whereNull('deleted_at')->where('employee_id', $event->employee->id)->first();
        if ($user === null) {
            return;
        }

        $actor = Auth::user();
        $this->access->moveHomeOrganization(
            $user,
            $event->fromOrganizationId,
            $event->employee->organization_id,
            $actor instanceof User ? $actor : null,
        );
    }
}
