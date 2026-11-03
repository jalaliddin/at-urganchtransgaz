<?php

namespace App\Services\Users;

use App\Enums\AuditEvent;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use App\Notifications\PasswordResetByAdmin;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\SessionRevoker;
use App\Support\Access\OrganizationAccessResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private readonly AccessManager $access,
        private readonly AuditLogger $audit,
        private readonly SessionRevoker $sessions,
        private readonly TemporaryPassword $passwords,
        private readonly OrganizationAccessResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated (roles: list<string>, organization_access?: list<array>)
     * @return array{user: User, temporary_password: string}
     *
     * @throws AuthorizationException when a requested role is above the actor's privileges
     */
    public function create(array $data, User $actor): array
    {
        $roles = Role::query()->whereIn('name', $data['roles'])->with('permissions')->get();
        foreach ($roles as $role) {
            if (! $this->access->canAssignRole($role, $actor)) {
                throw new AuthorizationException(__('messages.role_not_assignable', ['role' => $role->display_name ?: $role->name]));
            }
        }

        $temporary = $this->passwords->generate();

        $user = DB::transaction(function () use ($data, $roles, $temporary, $actor): User {
            if (! empty($data['employee_id'])) {
                $data['organization_id'] = Employee::withoutGlobalScopes()->findOrFail($data['employee_id'])->organization_id;
            }

            $user = User::create([
                ...Arr::only($data, ['username', 'name', 'email', 'locale', 'organization_id', 'employee_id']),
                'password' => $temporary,
                'must_change_password' => true,
                'is_active' => true,
            ]);

            $access = $data['organization_access']
                ?? ($user->organization_id ? [['organization_id' => $user->organization_id, 'include_descendants' => false]] : []);
            foreach ($access as $row) {
                UserOrganizationAccess::create([
                    'user_id' => $user->id,
                    'organization_id' => $row['organization_id'],
                    'include_descendants' => (bool) $row['include_descendants'],
                ]);
            }

            $user->syncRoles($roles);
            $this->audit->record(AuditEvent::RolesChanged, $user, ['roles' => []], ['roles' => $roles->pluck('name')->sort()->values()->all()], $actor);

            return $user;
        });

        return ['user' => $user->refresh(), 'temporary_password' => $temporary];
    }

    /**
     * @param  array<string, mixed>  $data  validated
     *
     * @throws AuthorizationException when the target is more privileged than the actor
     */
    public function update(User $target, array $data, User $actor): User
    {
        $this->access->assertCanAdminister($target, $actor, allowSelf: true);

        return DB::transaction(function () use ($target, $data, $actor): User {
            if (! empty($data['employee_id'])) {
                $data['organization_id'] = Employee::withoutGlobalScopes()->findOrFail($data['employee_id'])->organization_id;
            }
            $fromOrganizationId = $target->organization_id;
            $target->update(Arr::only($data, ['name', 'email', 'locale', 'employee_id']));

            if (array_key_exists('organization_id', $data)) {
                $to = $data['organization_id'] === null ? null : (int) $data['organization_id'];
                $this->access->moveHomeOrganization($target, $fromOrganizationId, $to, $actor);
            }

            return $target->refresh();
        });
    }

    public function deactivate(User $target, ?User $actor): void
    {
        $this->access->assertCanManage($target, $actor);
        if ($target->is_active && $target->isSuperAdmin()) {
            $this->access->ensureAnotherActiveSuperAdmin($target);
        }

        DB::transaction(fn () => $target->update(['is_active' => false]));
        $this->sessions->revokeAll($target);
        $this->resolver->forget($target);
    }

    public function activate(User $target, ?User $actor): void
    {
        $this->access->assertCanManage($target, $actor);

        DB::transaction(fn () => $target->update(['is_active' => true]));
    }

    /** @return string the temporary password (shown to the administrator once) */
    public function resetPassword(User $target, User $actor): string
    {
        $this->access->assertCanManage($target, $actor);
        $temporary = $this->passwords->generate();

        DB::transaction(function () use ($target, $temporary, $actor): void {
            $target->forceFill(['password' => $temporary, 'must_change_password' => true])->saveQuietly();
            $this->audit->record(AuditEvent::PasswordReset, $target, actor: $actor);
        });

        $this->sessions->revokeAll($target);
        $target->notify(new PasswordResetByAdmin($actor->name));

        return $temporary;
    }
}
