<?php

namespace Tests\Feature\Employees;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $b;

    private User $subA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($this->root)->create();
        $this->b = Organization::factory()->childOf($this->root)->create();
        $this->subA = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $this->a->id,
            'last_name' => 'Karimov',
            'first_name' => 'Aziz',
            'middle_name' => 'Rustamovich',
            'position' => 'Muhandis-dasturchi',
            'personnel_number' => '00412',
            'phone' => '+998901234567',
            'status' => 'active',
            'hired_at' => '2024-03-01',
        ], $overrides);
    }

    private function ids(TestResponse $response): array
    {
        return collect($response->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_central_staff_can_create_and_update_but_not_delete(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_STAFF, $this->root, true));

        $id = $this->postJson('/api/v1/employees', $this->payload())->assertCreated()
            ->assertJsonPath('data.full_name', 'Karimov Aziz Rustamovich')->json('data.id');
        $this->putJson("/api/v1/employees/{$id}", ['position' => 'Bosh muhandis'])->assertOk();
        $this->deleteJson("/api/v1/employees/{$id}")->assertForbidden();
    }

    public function test_org_it_responsible_full_crud_in_own_organization(): void
    {
        $this->actingAs($this->subA);

        $id = $this->postJson('/api/v1/employees', $this->payload())->assertCreated()->json('data.id');
        $this->getJson("/api/v1/employees/{$id}")->assertOk()->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.can.update', true);
        $this->deleteJson("/api/v1/employees/{$id}")->assertOk();
        $this->assertSoftDeleted('employees', ['id' => $id]);
    }

    public function test_create_in_inaccessible_organization_is_rejected(): void
    {
        $this->actingAs($this->subA);

        $this->postJson('/api/v1/employees', $this->payload(['organization_id' => $this->b->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['organization_id']);
    }

    public function test_department_from_other_organization_is_rejected(): void
    {
        $foreign = Department::factory()->for($this->b)->create();
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true));

        $this->postJson('/api/v1/employees', $this->payload(['department_id' => $foreign->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['department_id']);
    }

    public function test_other_organization_employee_is_invisible(): void
    {
        $foreign = Employee::factory()->for($this->b)->create();
        $this->actingAs($this->subA);

        $this->getJson("/api/v1/employees/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/employees/{$foreign->id}", ['position' => 'X'])->assertNotFound();
        $this->deleteJson("/api/v1/employees/{$foreign->id}")->assertNotFound();
        $this->assertNotContains($foreign->id, $this->ids($this->getJson('/api/v1/employees')));
    }

    public function test_filters_by_status_and_department(): void
    {
        $department = Department::factory()->for($this->a)->create();
        $inDepartment = Employee::factory()->for($this->a)->create(['department_id' => $department->id]);
        $dismissed = Employee::factory()->for($this->a)->dismissed()->create();
        $this->actingAs($this->subA);

        $this->assertSame([$dismissed->id], $this->ids($this->getJson('/api/v1/employees?filter[status]=dismissed')));
        $this->assertSame([$inDepartment->id], $this->ids($this->getJson("/api/v1/employees?filter[department]={$department->id}")));
    }

    public function test_search_matches_uzbek_apostrophe_variants(): void
    {
        $gulomov = Employee::factory()->for($this->a)->create(['last_name' => 'Gʻulomov']);
        $orozov = Employee::factory()->for($this->a)->create(['last_name' => "O'rozov"]);
        $this->actingAs($this->subA);

        $this->assertSame([$gulomov->id], $this->ids($this->getJson("/api/v1/employees?search=G'ulomov")));
        $this->assertSame([$orozov->id], $this->ids($this->getJson('/api/v1/employees?search=Oʻroz')));
    }

    public function test_search_wildcards_are_literal(): void
    {
        Employee::factory()->for($this->a)->count(2)->create();
        $this->actingAs($this->subA);

        $this->assertSame([], $this->ids($this->getJson('/api/v1/employees?search=%25')));
        $this->assertSame([], $this->ids($this->getJson('/api/v1/employees?search=_')));
    }

    public function test_personnel_number_unique_including_soft_deleted(): void
    {
        $old = Employee::factory()->for($this->a)->create(['personnel_number' => '00412']);
        $old->delete();
        $this->actingAs($this->subA);

        $this->postJson('/api/v1/employees', $this->payload())->assertStatus(422)->assertJsonValidationErrors(['personnel_number']);
    }

    public function test_dismissed_requires_dismissed_at_and_phone_is_validated(): void
    {
        $this->actingAs($this->subA);

        $this->postJson('/api/v1/employees', $this->payload(['status' => 'dismissed']))
            ->assertStatus(422)->assertJsonValidationErrors(['dismissed_at']);
        $this->postJson('/api/v1/employees', $this->payload(['phone' => 'call me']))
            ->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }
}
