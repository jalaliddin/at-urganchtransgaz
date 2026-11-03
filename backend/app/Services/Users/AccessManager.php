<?php

namespace App\Services\Users;

use App\Enums\AuditEvent;
use App\Exceptions\DomainConflictException;
use App\Models\Role;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use App\Notifications\AccessChanged;
use App\Services\Audit\AuditLogger;
use App\Support\Access\OrganizationAccessResolver;
use App\Support\Permissions\Roles;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Role and organization-access changes with privilege-escalation guards.
 * $actor = null means a trusted system caller (CLI, listeners): guards that
 * depend on the actor are skipped, structural guards still apply.
 */
class AccessManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OrganizationAccessResolver $resolver,
    ) {}

    /** Roles, access, status and passwords: never on your own account. */
    public function assertCanManage(User $target, ?User $actor): void
    {
        $this->assertCanAdminister($target, $actor, allowSelf: false);
    }

    /**
     * The one rule for acting on another account (or on its employee record):
     *  - only a Super Admin administers a Super Admin;
     *  - otherwise the target's permissions and organization scope must both be
     *    subsets of the actor's, so no one can take over a more powerful account.
     * $actor = null is a trusted system caller.
     */
    public function assertCanAdminister(User $target, ?User $actor, bool $allowSelf = false): void
    {
        if ($actor === null) {
            return;
        }
        if ($target->is($actor)) {
            if ($allowSelf) {
                return;
            }
            throw new AuthorizationException(__('messages.cannot_manage_self'));
        }
        if ($actor->isSuperAdmin()) {
            return;
        }
        if ($target->isSuperAdmin()) {
            throw new AuthorizationException(__('messages.target_more_privileged'));
        }

        $actorPermissions = $actor->getAllPermissions()->pluck('name');
        if ($target->getAllPermissions()->pluck('name')->diff($actorPermissions)->isNotEmpty()) {
            throw new AuthorizationException(__('messages.target_more_privileged'));
        }

        $actorScope = $actor->accessScope();
        foreach ($target->accessScope()->organizationIds() as $organizationId) {
            if (! $actorScope->allows($organizationId)) {
                throw new AuthorizationException(__('messages.target_more_privileged'));
            }
        }
    }

    /**
     * Moves a user's home organization: the plain (non-descendant) access row of the
     * old home is replaced by one for the new home; deliberately granted subtree
     * rows stay. Audited as access_changed.
     */
    public function moveHomeOrganization(User $user, ?int $fromOrganizationId, ?int $toOrganizationId, ?User $actor): void
    {
        if ($fromOrganizationId === $toOrganizationId) {
            return;
        }

        $before = $this->snapshot(UserOrganizationAccess::where('user_id', $user->id)->get()->all());

        DB::transaction(function () use ($user, $fromOrganizationId, $toOrganizationId): void {
            if ($fromOrganizationId !== null) {
                UserOrganizationAccess::where('user_id', $user->id)
                    ->where('organization_id', $fromOrganizationId)
                    ->where('include_descendants', false)
                    ->delete();
            }
            if ($toOrganizationId !== null) {
                UserOrganizationAccess::firstOrCreate(
                    ['user_id' => $user->id, 'organization_id' => $toOrganizationId],
                    ['include_descendants' => false],
                );
            }
            $user->update(['organization_id' => $toOrganizationId]);
        });

        $after = $this->snapshot(UserOrganizationAccess::where('user_id', $user->id)->get()->all());
        if ($before !== $after) {
            $this->audit->record(AuditEvent::AccessChanged, $user, ['access' => $before], ['access' => $after], $actor);
        }
        $this->resolver->forget($user);
    }

    /** A role may be granted (or removed) only if its permissions are a subset of the actor's. */
    public function canAssignRole(Role $role, ?User $actor): bool
    {
        if ($actor === null || $actor->isSuperAdmin()) {
            return true;
        }
        if ($role->name === Roles::SUPER_ADMIN) {
            return false;
        }

        $own = $actor->getAllPermissions()->pluck('name');

        return $role->permissions->pluck('name')->diff($own)->isEmpty();
    }

    /**
     * @param  list<string>  $roleNames
     *
     * @throws AuthorizationException|DomainConflictException
     */
    public function syncRoles(User $target, array $roleNames, ?User $actor, bool $notify = true): void
    {
        $this->assertCanManage($target, $actor);

        $old = $target->roles()->pluck('name')->sort()->values()->all();
        $new = collect($roleNames)->unique()->sort()->values()->all();
        if ($old === $new) {
            return;
        }

        $touched = array_merge(array_diff($new, $old), array_diff($old, $new));
        foreach (Role::query()->whereIn('name', $touched)->with('permissions')->get() as $role) {
            if (! $this->canAssignRole($role, $actor)) {
                throw new AuthorizationException(__('messages.role_not_assignable', ['role' => $role->display_name ?: $role->name]));
            }
        }

        if (in_array(Roles::SUPER_ADMIN, $old, true) && ! in_array(Roles::SUPER_ADMIN, $new, true)) {
            $this->ensureAnotherActiveSuperAdmin($target);
        }

        DB::transaction(function () use ($target, $new, $old, $actor): void {
            $target->syncRoles($new);
            $this->audit->record(AuditEvent::RolesChanged, $target, ['roles' => $old], ['roles' => $new], $actor);
        });

        $this->resolver->forget($target);
        if ($notify) {
            $target->notify(new AccessChanged($actor->name ?? __('messages.system')));
        }
    }

    /**
     * Replaces the target's access rows that lie inside the actor's scope; rows the
     * actor cannot see are kept untouched. Callers validate $access against the actor.
     *
     * @param  list<array{organization_id: int, include_descendants: bool}>  $access
     */
    public function syncOrganizationAccess(User $target, array $access, ?User $actor, bool $notify = true): void
    {
        $this->assertCanManage($target, $actor);
        $scope = $actor?->accessScope();

        $existing = UserOrganizationAccess::where('user_id', $target->id)->get();
        $before = $this->snapshot($existing->all());

        $final = [];
        foreach ($existing as $row) {
            if ($scope !== null && ! $scope->allows($row->organization_id)) {
                $final[$row->organization_id] = ['organization_id' => $row->organization_id, 'include_descendants' => $row->include_descendants];
            }
        }
        foreach ($access as $row) {
            if ($scope !== null && ! $scope->allows($row['organization_id'])) {
                throw new AuthorizationException;
            }
            $final[$row['organization_id']] = [
                'organization_id' => (int) $row['organization_id'],
                'include_descendants' => (bool) $row['include_descendants'],
            ];
        }

        $after = $this->snapshot(array_values($final));
        if ($before === $after) {
            return;
        }

        DB::transaction(function () use ($target, $final, $before, $after, $actor): void {
            UserOrganizationAccess::where('user_id', $target->id)->delete();
            foreach ($final as $row) {
                UserOrganizationAccess::create(['user_id' => $target->id, ...$row]);
            }
            $this->audit->record(AuditEvent::AccessChanged, $target, ['access' => $before], ['access' => $after], $actor);
        });

        $this->resolver->forget($target);
        if ($notify) {
            $target->notify(new AccessChanged($actor->name ?? __('messages.system')));
        }
    }

    /** @throws DomainConflictException */
    public function ensureAnotherActiveSuperAdmin(User $target): void
    {
        $others = User::withoutGlobalScopes()->role(Roles::SUPER_ADMIN)
            ->where('is_active', true)->whereNull('deleted_at')
            ->whereKeyNot($target->id)->count();

        if ($others === 0) {
            throw new DomainConflictException(__('messages.last_super_admin'));
        }
    }

    /**
     * @param  list<UserOrganizationAccess|array{organization_id: int, include_descendants: bool}>  $rows
     * @return list<array{organization_id: int, include_descendants: bool}>
     */
    private function snapshot(array $rows): array
    {
        $result = array_map(fn ($row): array => [
            'organization_id' => (int) ($row instanceof UserOrganizationAccess ? $row->organization_id : $row['organization_id']),
            'include_descendants' => (bool) ($row instanceof UserOrganizationAccess ? $row->include_descendants : $row['include_descendants']),
        ], $rows);
        usort($result, fn (array $a, array $b): int => $a['organization_id'] <=> $b['organization_id']);

        return $result;
    }
}
