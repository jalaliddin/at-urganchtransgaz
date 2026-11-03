<?php

namespace App\Support\Permissions;

/**
 * Read-only view of config/permissions.php.
 */
class PermissionRegistry
{
    /** @return list<string> every permission name, module order preserved */
    public function all(): array
    {
        return array_merge(...array_values($this->modules()));
    }

    /** @return array<string, list<string>> module => full permission names */
    public function modules(): array
    {
        $modules = [];
        foreach ((array) config('permissions.modules', []) as $module => $actions) {
            $modules[(string) $module] = array_values(array_map(
                fn (string $action): string => "{$module}.{$action}",
                (array) $actions,
            ));
        }

        return $modules;
    }

    /**
     * @return array<string, array{display_name: string, description: string, permissions: list<string>}>
     */
    public function defaultRoles(): array
    {
        $roles = [];
        foreach ((array) config('permissions.roles', []) as $name => $definition) {
            $permissions = $definition['permissions'] ?? [];
            $roles[(string) $name] = [
                'display_name' => (string) ($definition['display_name'] ?? $name),
                'description' => (string) ($definition['description'] ?? ''),
                'permissions' => $permissions === '*' ? $this->all() : array_values((array) $permissions),
            ];
        }

        return $roles;
    }
}
