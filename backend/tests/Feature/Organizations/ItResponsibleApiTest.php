<?php

namespace Tests\Feature\Organizations;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationItResponsible;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ItResponsibleApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($this->root)->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true);
    }

    private function assign(Employee $employee, bool $primary = false): TestResponse
    {
        return $this->postJson("/api/v1/organizations/{$this->a->id}/it-responsibles", [
            'employee_id' => $employee->id, 'is_primary' => $primary, 'assigned_at' => '2026-11-01',
        ]);
    }

    public function test_assign_and_list(): void
    {
        $employee = Employee::factory()->for($this->root)->create(['last_name' => 'Karimov', 'first_name' => 'Aziz']);
        $this->actingAs($this->head);

        $this->assign($employee, true)->assertCreated()->assertJsonPath('data.employee.full_name', 'Karimov Aziz');

        $this->getJson("/api/v1/organizations/{$this->a->id}/it-responsibles")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_primary', true)
            ->assertJsonPath('data.0.assigned_at', '2026-11-01');
    }

    public function test_subsidiary_user_sees_central_responsible(): void
    {
        $employee = Employee::factory()->for($this->root)->create();
        OrganizationItResponsible::factory()->create(['organization_id' => $this->a->id, 'employee_id' => $employee->id]);
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));

        $this->getJson("/api/v1/organizations/{$this->a->id}/it-responsibles")
            ->assertOk()->assertJsonPath('data.0.employee.id', $employee->id);
    }

    public function test_only_one_primary(): void
    {
        $first = Employee::factory()->for($this->a)->create();
        $second = Employee::factory()->for($this->a)->create();
        $this->actingAs($this->head);

        $this->assign($first, true)->assertCreated();
        $this->assign($second, true)->assertCreated();

        $this->assertFalse(OrganizationItResponsible::where('employee_id', $first->id)->first()->is_primary);
        $this->assertTrue(OrganizationItResponsible::where('employee_id', $second->id)->first()->is_primary);
    }

    public function test_duplicate_assignment_is_rejected(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $this->actingAs($this->head);

        $this->assign($employee)->assertCreated();
        $this->assign($employee)->assertStatus(422)->assertJsonValidationErrors(['employee_id']);
    }

    public function test_remove(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $this->actingAs($this->head);
        $this->assign($employee)->assertCreated();

        $this->deleteJson("/api/v1/organizations/{$this->a->id}/it-responsibles/{$employee->id}")->assertOk();
        $this->deleteJson("/api/v1/organizations/{$this->a->id}/it-responsibles/{$employee->id}")->assertNotFound();
        $this->assertSame(0, OrganizationItResponsible::count());
    }

    public function test_subsidiary_user_cannot_manage(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a));

        $this->assign($employee)->assertForbidden();
    }

    public function test_employee_outside_actor_scope_is_rejected(): void
    {
        $other = Organization::factory()->childOf($this->root)->create();
        $outsider = Employee::factory()->for($other)->create();
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a, true));

        $this->assign($outsider)->assertStatus(422)->assertJsonValidationErrors(['employee_id']);
    }
}
