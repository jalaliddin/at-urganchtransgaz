<?php

namespace App\Services\Roles;

use App\Enums\AuditEvent;
use App\Exceptions\DomainConflictException;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /** @param array{name: string, display_name: string, description?: string|null, permissions: list<string>} $data */
    public function create(array $data): Role
    {
        $role = DB::transaction(function () use ($data): Role {
            // new + save (not Role::create) keeps the concrete model type; uniqueness is validated.
            $role = new Role([
                'name' => $data['name'],
                'guard_name' => 'web',
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
            ]);
            $role->save();
            $role->syncPermissions($data['permissions']);
            $this->audit->record(AuditEvent::RolePermissionsChanged, $role, ['permissions' => []], ['permissions' => $this->names($data['permissions'])]);

            return $role;
        });
        $this->registrar->forgetCachedPermissions();

        return $role->refresh();
    }

    /** @param array{display_name?: string, description?: string|null, permissions?: list<string>} $data */
    public function update(Role $role, array $data): Role
    {
        DB::transaction(function () use ($role, $data): void {
            $role->update(array_intersect_key($data, array_flip(['display_name', 'description'])));

            if (array_key_exists('permissions', $data)) {
                $old = $this->names($role->permissions->pluck('name')->all());
                $new = $this->names($data['permissions']);
                if ($old !== $new) {
                    $role->syncPermissions($new);
                    $this->audit->record(AuditEvent::RolePermissionsChanged, $role, ['permissions' => $old], ['permissions' => $new]);
                }
            }
        });
        $this->registrar->forgetCachedPermissions();

        return $role->refresh();
    }

    /** @throws DomainConflictException for system roles or roles still assigned */
    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw new DomainConflictException(__('messages.role_is_system'));
        }
        if (DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->exists()) {
            throw new DomainConflictException(__('messages.role_in_use'));
        }

        DB::transaction(fn () => $role->delete());
        $this->registrar->forgetCachedPermissions();
    }

    /**
     * @param  array<int, string>  $names
     * @return list<string>
     */
    private function names(array $names): array
    {
        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
