<?php

namespace Tests\Feature\Departments;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $a;

    private Organization $b;

    private User $head;

    private User $subA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($root)->create();
        $this->b = Organization::factory()->childOf($root)->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $root, true);
        $this->subA = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);
    }

    public function test_org_it_responsible_crud_in_own_organization(): void
    {
        $this->actingAs($this->subA);

        $id = $this->postJson('/api/v1/departments', ['organization_id' => $this->a->id, 'name' => 'AT bo\'limi', 'code' => 'AT'])
            ->assertCreated()->assertJsonPath('data.organization.id', $this->a->id)->json('data.id');
        $this->getJson('/api/v1/departments')->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/v1/departments/{$id}", ['name' => 'Axborot texnologiyalari bo\'limi'])
            ->assertOk()->assertJsonPath('data.name', 'Axborot texnologiyalari bo\'limi');
        $this->deleteJson("/api/v1/departments/{$id}")->assertOk();
        $this->assertSoftDeleted('departments', ['id' => $id]);
    }

    public function test_other_organization_department_is_invisible(): void
    {
        $foreign = Department::factory()->for($this->b)->create();
        $this->actingAs($this->subA);

        $this->getJson("/api/v1/departments/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/departments/{$foreign->id}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("/api/v1/departments/{$foreign->id}")->assertNotFound();
        $this->postJson('/api/v1/departments', ['organization_id' => $this->b->id, 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors(['organization_id']);
    }

    public function test_parent_must_belong_to_the_same_organization(): void
    {
        $foreignParent = Department::factory()->for($this->b)->create();
        $this->actingAs($this->head);

        $this->postJson('/api/v1/departments', ['organization_id' => $this->a->id, 'name' => 'X', 'parent_id' => $foreignParent->id])
            ->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
    }

    public function test_cannot_nest_department_under_itself_or_descendant(): void
    {
        $parent = Department::factory()->for($this->a)->create();
        $child = Department::factory()->for($this->a)->create(['parent_id' => $parent->id]);
        $this->actingAs($this->head);

        $this->putJson("/api/v1/departments/{$parent->id}", ['parent_id' => $child->id])->assertJsonValidationErrors(['parent_id']);
        $this->putJson("/api/v1/departments/{$parent->id}", ['parent_id' => $parent->id])->assertJsonValidationErrors(['parent_id']);
    }

    public function test_code_is_unique_per_organization(): void
    {
        Department::factory()->for($this->a)->create(['code' => 'AT']);
        $this->actingAs($this->head);

        $this->postJson('/api/v1/departments', ['organization_id' => $this->a->id, 'name' => 'X', 'code' => 'AT'])
            ->assertJsonValidationErrors(['code']);
        $this->postJson('/api/v1/departments', ['organization_id' => $this->b->id, 'name' => 'X', 'code' => 'AT'])
            ->assertCreated();
    }

    public function test_delete_with_employees_or_children_conflicts(): void
    {
        $withEmployee = Department::factory()->for($this->a)->create();
        Employee::factory()->for($this->a)->create(['department_id' => $withEmployee->id]);
        $withChild = Department::factory()->for($this->a)->create();
        Department::factory()->for($this->a)->create(['parent_id' => $withChild->id]);
        $this->actingAs($this->head);

        $this->deleteJson("/api/v1/departments/{$withEmployee->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/departments/{$withChild->id}")->assertStatus(409);
    }

    public function test_organization_change_is_prohibited(): void
    {
        $department = Department::factory()->for($this->a)->create();
        $this->actingAs($this->head);

        $this->putJson("/api/v1/departments/{$department->id}", ['organization_id' => $this->b->id])
            ->assertStatus(422)->assertJsonValidationErrors(['organization_id']);
    }

    public function test_org_head_can_view_but_not_create(): void
    {
        Department::factory()->for($this->a)->create();
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));

        $this->getJson('/api/v1/departments')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/departments', ['organization_id' => $this->a->id, 'name' => 'X'])->assertForbidden();
    }

    public function test_filter_by_organization_subtree(): void
    {
        Department::factory()->for($this->a)->create();
        Department::factory()->for($this->b)->create();
        $this->actingAs($this->head);

        $this->getJson("/api/v1/departments?filter[organization]={$this->a->id}")->assertJsonCount(1, 'data');
    }
}
