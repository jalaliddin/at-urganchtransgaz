<?php

namespace Tests\Unit;

use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\Roles;
use Tests\TestCase;

class PermissionRegistryTest extends TestCase
{
    public function test_all_lists_module_action_names(): void
    {
        $all = app(PermissionRegistry::class)->all();

        $this->assertContains('employees.update', $all);
        $this->assertContains('organizations.manage_it_staff', $all);
        $this->assertSame(array_values(array_unique($all)), $all);
    }

    public function test_modules_group_full_names(): void
    {
        $modules = app(PermissionRegistry::class)->modules();

        $this->assertSame(['settings.view', 'settings.update'], $modules['settings']);
    }

    public function test_star_expands_to_all_permissions(): void
    {
        $registry = app(PermissionRegistry::class);

        $this->assertSame($registry->all(), $registry->defaultRoles()[Roles::SUPER_ADMIN]['permissions']);
    }

    public function test_every_role_permission_exists_in_registry(): void
    {
        $registry = app(PermissionRegistry::class);

        foreach ($registry->defaultRoles() as $role => $definition) {
            $this->assertSame([], array_diff($definition['permissions'], $registry->all()), $role);
        }
        $this->assertSame(Roles::ALL, array_keys($registry->defaultRoles()));
    }
}
