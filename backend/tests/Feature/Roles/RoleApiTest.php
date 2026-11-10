<?php

namespace Tests\Feature\Roles;

use App\Models\Organization;
use App\Models\Role;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_super_admin_creates_and_updates_a_role(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $id = $this->postJson('/api/v1/roles', [
            'name' => 'helpdesk-operator', 'display_name' => 'Helpdesk operatori',
            'permissions' => ['dashboard.view', 'employees.view'],
        ])->assertCreated()->assertJsonPath('data.is_system', false)->json('data.id');

        $this->putJson("/api/v1/roles/{$id}", ['permissions' => ['dashboard.view']])
            ->assertOk()->assertJsonPath('data.permissions', ['dashboard.view']);
    }

    public function test_a_permission_the_code_declares_but_the_database_lacks_is_created_when_a_role_takes_it(): void
    {
        // A deploy that pulled new code but did not run RbacSeeder: the editor already offers printers.manage.
        Permission::query()->where('name', 'printers.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));
        $role = Role::findByName(Roles::ORG_IT_RESPONSIBLE, 'web');

        $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => [...$role->permissions->pluck('name')->all(), 'printers.manage']])
            ->assertOk()->assertJsonFragment(['printers.manage']);
        $this->postJson('/api/v1/roles', ['name' => 'printer-admin', 'display_name' => 'Printer admin', 'permissions' => ['printers.manage']])
            ->assertCreated()->assertJsonPath('data.permissions', ['printers.manage']);
        $this->putJson("/api/v1/roles/{$role->id}", ['permissions' => ['dashboard.view', 'no.such']])
            ->assertStatus(422)->assertJsonValidationErrors('permissions.1');
        $this->assertSame(1, Permission::query()->where('name', 'printers.manage')->count());
    }

    public function test_validation(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->postJson('/api/v1/roles', ['name' => 'x', 'display_name' => 'X', 'permissions' => ['nope.view']])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'permissions.0']);
        $this->postJson('/api/v1/roles', ['name' => Roles::AUDITOR, 'display_name' => 'X', 'permissions' => []])
            ->assertJsonValidationErrors(['name']);
    }

    public function test_delete_rules(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));
        $system = Role::findByName(Roles::AUDITOR);
        $custom = Role::create(['name' => 'temp-role', 'guard_name' => 'web', 'display_name' => 'Temp']);
        $used = Role::create(['name' => 'used-role', 'guard_name' => 'web', 'display_name' => 'Used']);
        $this->makeUser('used-role', Organization::factory()->create());

        $this->deleteJson("/api/v1/roles/{$system->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/roles/{$used->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/roles/{$custom->id}")->assertOk();
        $this->assertNull(Role::where('name', 'temp-role')->first());
    }

    public function test_head_can_view_but_not_manage_and_auditor_can_view(): void
    {
        $root = Organization::factory()->create();
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $root, true));
        $this->getJson('/api/v1/roles')->assertOk()->assertJsonCount(8, 'data');
        $this->postJson('/api/v1/roles', ['name' => 'abc', 'display_name' => 'X', 'permissions' => []])->assertForbidden();

        $this->actingAs($this->makeUser(Roles::AUDITOR, $root, true));
        $this->getJson('/api/v1/permissions')->assertOk()->assertJsonPath('data.0.module', 'dashboard');
    }

    public function test_employee_cannot_view_roles(): void
    {
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, Organization::factory()->create()));

        $this->getJson('/api/v1/roles')->assertForbidden();
    }
}
