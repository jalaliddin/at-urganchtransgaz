<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Events\EmployeeTransferred;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use App\Services\Audit\AuditLogger;
use App\Support\Access\OrganizationAccessResolver;

/**
 * A transferred employee's account follows them: home organization changes and the
 * plain (non-descendant) access to the old organization is replaced by the new one.
 * Access rows with include_descendants were granted deliberately and are kept.
 */
class SyncLinkedUserOrganization
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OrganizationAccessResolver $access,
    ) {}

    public function handle(EmployeeTransferred $event): void
    {
        $employee = $event->employee;
        $user = User::withoutGlobalScopes()->where('employee_id', $employee->id)->first();
        if ($user === null) {
            return;
        }

        $before = $this->snapshot($user);

        UserOrganizationAccess::where('user_id', $user->id)
            ->where('organization_id', $event->fromOrganizationId)
            ->where('include_descendants', false)
            ->delete();
        UserOrganizationAccess::firstOrCreate(
            ['user_id' => $user->id, 'organization_id' => $employee->organization_id],
            ['include_descendants' => false],
        );
        $user->update(['organization_id' => $employee->organization_id]);

        $this->audit->record(AuditEvent::AccessChanged, $user, ['access' => $before], ['access' => $this->snapshot($user)]);
        $this->access->forget($user);
    }

    /** @return list<array{organization_id: int, include_descendants: bool}> */
    private function snapshot(User $user): array
    {
        return UserOrganizationAccess::where('user_id', $user->id)->orderBy('organization_id')->get()
            ->map(fn (UserOrganizationAccess $row): array => [
                'organization_id' => $row->organization_id,
                'include_descendants' => $row->include_descendants,
            ])->values()->all();
    }
}
