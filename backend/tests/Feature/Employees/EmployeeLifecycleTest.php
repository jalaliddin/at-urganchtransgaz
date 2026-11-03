<?php

namespace Tests\Feature\Employees;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class EmployeeLifecycleTest extends TestCase
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

    private function linkedUser(Employee $employee): User
    {
        $user = User::factory()->forEmployee($employee)->create();
        $this->grantAccess($user, $employee->organization);

        return $user;
    }

    public function test_dismissal_deactivates_linked_user_and_revokes_sessions(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $user = $this->linkedUser($employee);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->head);

        $this->putJson("/api/v1/employees/{$employee->id}", ['status' => 'dismissed', 'dismissed_at' => '2026-11-02'])->assertOk();

        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_transfer_moves_user_home_organization_and_access(): void
    {
        $department = Department::factory()->for($this->a)->create();
        $employee = Employee::factory()->for($this->a)->create(['department_id' => $department->id]);
        $user = $this->linkedUser($employee);
        $extra = Organization::factory()->childOf($this->root)->create();
        $this->grantAccess($user, $extra, true); // granted by an admin — must survive
        $this->actingAs($this->head);

        $this->putJson("/api/v1/employees/{$employee->id}", ['organization_id' => $this->b->id])
            ->assertOk()->assertJsonPath('data.department', null);

        $this->assertSame($this->b->id, $user->fresh()->organization_id);
        $rows = UserOrganizationAccess::where('user_id', $user->id)->orderBy('organization_id')
            ->get(['organization_id', 'include_descendants'])->toArray();
        $this->assertEqualsCanonicalizing([
            ['organization_id' => $this->b->id, 'include_descendants' => false],
            ['organization_id' => $extra->id, 'include_descendants' => true],
        ], $rows);
        $this->assertSame(1, AuditLog::where('event', 'access_changed')->where('auditable_id', $user->id)->count());
    }

    public function test_transfer_requires_access_to_target_organization(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a));

        $this->putJson("/api/v1/employees/{$employee->id}", ['organization_id' => $this->b->id])
            ->assertStatus(422)->assertJsonValidationErrors(['organization_id']);
    }

    public function test_delete_with_linked_user_conflicts(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $this->linkedUser($employee);
        $this->actingAs($this->head);

        $this->deleteJson("/api/v1/employees/{$employee->id}")->assertStatus(409);
    }
}
