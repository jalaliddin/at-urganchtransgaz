<?php

namespace Tests\Feature\Regression;

use App\Exceptions\DomainConflictException;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use App\Services\Employees\EmployeeService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

/**
 * Regression tests for the whole-branch review findings (C1, I1–I4, I6).
 */
class FinalReviewTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $b;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($this->root)->create();
        $this->b = Organization::factory()->childOf($this->root)->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true);
    }

    private function linkUser(Employee $employee, string $role): User
    {
        $user = User::factory()->forEmployee($employee)->create();
        $user->assignRole($role);
        $this->grantAccess($user, $employee->organization);

        return $user->fresh();
    }

    // C1 — employee side effects must respect user-management guards

    public function test_dismissing_a_super_admins_employee_record_requires_a_super_admin(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $super = $this->linkUser($employee, Roles::SUPER_ADMIN);
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a));

        $this->putJson("/api/v1/employees/{$employee->id}", ['status' => 'dismissed', 'dismissed_at' => '2026-11-02'])
            ->assertForbidden();

        $this->assertTrue($super->fresh()->is_active);
        $this->assertSame('active', $employee->fresh()->status->value);
    }

    public function test_dismissing_a_more_privileged_users_employee_is_forbidden(): void
    {
        $employee = Employee::factory()->for($this->root)->create();
        $headUser = $this->linkUser($employee, Roles::CENTRAL_IT_HEAD);
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_STAFF, $this->root, true));

        $this->putJson("/api/v1/employees/{$employee->id}", ['status' => 'dismissed', 'dismissed_at' => '2026-11-02'])
            ->assertForbidden();
        $this->putJson("/api/v1/employees/{$employee->id}", ['organization_id' => $this->a->id])->assertForbidden();

        $this->assertTrue($headUser->fresh()->is_active);
    }

    public function test_dismissing_an_ordinary_users_employee_still_works(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $plain = $this->linkUser($employee, Roles::EMPLOYEE);
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a));

        $this->putJson("/api/v1/employees/{$employee->id}", ['status' => 'dismissed', 'dismissed_at' => '2026-11-02'])->assertOk();

        $this->assertFalse($plain->fresh()->is_active);
    }

    public function test_system_dismissal_of_the_last_super_admin_is_refused(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $this->linkUser($employee, Roles::SUPER_ADMIN);

        $this->expectException(DomainConflictException::class);
        app(EmployeeService::class)->update($employee, ['status' => 'dismissed', 'dismissed_at' => '2026-11-02'], null);
    }

    // I1 — array search parameter must not crash list endpoints

    public function test_array_search_parameter_does_not_crash_lists(): void
    {
        $this->actingAs($this->head);

        foreach (['organizations', 'departments', 'employees', 'users'] as $resource) {
            $this->getJson("/api/v1/{$resource}?search[]=x")->assertOk();
        }
    }

    // I2 — unchanged parent / organization is not re-validated

    public function test_head_can_edit_the_root_organization_with_the_spa_payload(): void
    {
        $this->actingAs($this->head);

        $this->putJson("/api/v1/organizations/{$this->root->id}", ['parent_id' => null, 'name' => 'Markaziy apparat'])
            ->assertOk();
    }

    public function test_organization_under_an_inactive_parent_stays_editable(): void
    {
        $this->a->update(['is_active' => false]);
        $branch = Organization::factory()->childOf($this->a)->create();
        $this->actingAs($this->head);

        $this->putJson("/api/v1/organizations/{$branch->id}", ['parent_id' => $this->a->id, 'name' => 'Yangi nom'])->assertOk();
    }

    public function test_staff_of_an_inactive_organization_can_still_be_edited(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $this->a->update(['is_active' => false]);
        $this->actingAs($this->head);

        $this->putJson("/api/v1/employees/{$employee->id}", [
            'organization_id' => $this->a->id, 'status' => 'dismissed', 'dismissed_at' => '2026-11-02',
        ])->assertOk();
    }

    // I3 — home organization changes keep access consistent

    public function test_changing_home_organization_replaces_the_plain_access_row(): void
    {
        $target = $this->makeUser(Roles::EMPLOYEE, $this->a);
        $this->actingAs($this->head);

        $this->putJson("/api/v1/users/{$target->id}", ['organization_id' => $this->b->id])->assertOk();

        $this->assertSame(
            [['organization_id' => $this->b->id, 'include_descendants' => false]],
            UserOrganizationAccess::where('user_id', $target->id)->get(['organization_id', 'include_descendants'])->toArray(),
        );
    }

    public function test_restricted_admin_cannot_turn_a_user_into_a_system_account(): void
    {
        $target = $this->makeUser(Roles::EMPLOYEE, $this->a);
        $this->actingAs($this->head);

        $this->putJson("/api/v1/users/{$target->id}", ['organization_id' => null])
            ->assertStatus(422)->assertJsonValidationErrors(['organization_id']);
    }

    // I4 — no takeover of accounts with more permissions or wider scope

    public function test_cannot_reset_password_of_a_user_with_permissions_the_actor_lacks(): void
    {
        $powerful = Role::create(['name' => 'role-editor', 'guard_name' => 'web', 'display_name' => 'Role editor']);
        $powerful->givePermissionTo('roles.manage');
        $target = $this->makeUser('role-editor', $this->a);
        $this->actingAs($this->head);

        $this->postJson("/api/v1/users/{$target->id}/reset-password")->assertForbidden();
        $this->putJson("/api/v1/users/{$target->id}", ['email' => 'attacker@example.com'])->assertForbidden();
        $this->postJson("/api/v1/users/{$target->id}/deactivate")->assertForbidden();
    }

    public function test_cannot_manage_a_user_whose_scope_is_wider_than_the_actors(): void
    {
        $scopedHead = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a, true);
        $target = $this->makeUser(Roles::EMPLOYEE, $this->a);
        $this->grantAccess($target, $this->b);
        $this->actingAs($scopedHead);

        $this->postJson("/api/v1/users/{$target->id}/reset-password")->assertForbidden();
    }

    // I6 — organization delete guard

    public function test_soft_deleted_children_do_not_block_organization_deletion(): void
    {
        $org = Organization::factory()->childOf($this->root)->create();
        Organization::factory()->childOf($org)->create()->delete();
        Department::factory()->for($org)->create()->delete();
        Employee::factory()->for($org)->create()->delete();
        $this->actingAs($this->head);

        $this->deleteJson("/api/v1/organizations/{$org->id}")->assertOk();
    }

    public function test_organization_with_users_cannot_be_deleted(): void
    {
        $org = Organization::factory()->childOf($this->root)->create();
        $this->makeUser(Roles::EMPLOYEE, $org);
        $this->actingAs($this->head);

        $this->deleteJson("/api/v1/organizations/{$org->id}")->assertStatus(409);
    }
}
