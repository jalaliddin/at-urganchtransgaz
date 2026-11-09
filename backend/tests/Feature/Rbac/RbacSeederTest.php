<?php

namespace Tests\Feature\Rbac;

use App\Models\Role;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\Roles;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private function role(string $name): Role
    {
        return Role::findByName($name, 'web');
    }

    public function test_seeding_twice_is_idempotent(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(RbacSeeder::class);

        $this->assertSame(count(app(PermissionRegistry::class)->all()), Permission::count());
        $this->assertSame(8, Role::count());
        $this->assertTrue($this->role(Roles::AUDITOR)->is_system);
        $this->assertSame('Auditor / kuzatuvchi', $this->role(Roles::AUDITOR)->display_name);
    }

    public function test_default_matrix_is_applied(): void
    {
        $this->seed(RbacSeeder::class);

        $this->assertTrue($this->role(Roles::ORG_HEAD)->hasPermissionTo('employees.view'));
        $this->assertFalse($this->role(Roles::ORG_HEAD)->hasPermissionTo('employees.update'));
        $this->assertTrue($this->role(Roles::CENTRAL_IT_STAFF)->hasPermissionTo('employees.create'));
        $this->assertTrue($this->role(Roles::CENTRAL_IT_STAFF)->hasPermissionTo('employees.update'));
        $this->assertFalse($this->role(Roles::CENTRAL_IT_STAFF)->hasPermissionTo('employees.delete'));
        $this->assertSame(['dashboard.view', 'helpdesk.create', 'kb.view'], $this->role(Roles::EMPLOYEE)->permissions->pluck('name')->sort()->values()->all());
        $this->assertFalse($this->role(Roles::CENTRAL_IT_HEAD)->hasPermissionTo('roles.manage'));
        $this->assertTrue($this->role(Roles::SUPER_ADMIN)->hasPermissionTo('roles.manage'));
    }

    public function test_super_admin_passes_any_gate(): void
    {
        $this->seed(RbacSeeder::class);
        $user = $this->makeUser(Roles::SUPER_ADMIN);

        $this->assertTrue($user->can('anything.at_all'));
    }

    public function test_admin_removal_survives_reseed(): void
    {
        $this->seed(RbacSeeder::class);
        $this->role(Roles::AUDITOR)->revokePermissionTo('audit.view');

        $this->seed(RbacSeeder::class);

        $this->assertFalse($this->role(Roles::AUDITOR)->fresh()->hasPermissionTo('audit.view'));
    }

    public function test_new_permission_is_granted_to_default_roles_on_reseed(): void
    {
        $this->seed(RbacSeeder::class);
        config([
            'permissions.modules.audit' => ['view', 'export'],
            'permissions.roles.auditor.permissions' => [
                ...config('permissions.roles.auditor.permissions'), 'audit.export',
            ],
        ]);

        $this->seed(RbacSeeder::class);

        $this->assertTrue($this->role(Roles::AUDITOR)->fresh()->hasPermissionTo('audit.export'));
        $this->assertFalse($this->role(Roles::ORG_HEAD)->fresh()->hasPermissionTo('audit.export'));
    }
}
