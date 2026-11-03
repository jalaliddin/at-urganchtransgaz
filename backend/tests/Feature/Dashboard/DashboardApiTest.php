<?php

namespace Tests\Feature\Dashboard;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationItResponsible;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($this->root)->create();
        $this->b = Organization::factory()->childOf($this->root)->inactive()->create();
        Employee::factory()->for($this->a)->count(2)->create();
        Employee::factory()->for($this->a)->dismissed()->create();
        Employee::factory()->for($this->b)->create(['status' => 'on_leave']);
        OrganizationItResponsible::factory()->create(['organization_id' => $this->a->id, 'employee_id' => Employee::first()->id]);
    }

    public function test_central_head_gets_all_widgets_with_counts(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true));

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.organizations', ['total' => 3, 'active' => 2])
            ->assertJsonPath('data.employees.total', 4)
            ->assertJsonPath('data.employees.by_status', ['active' => 2, 'on_leave' => 1, 'business_trip' => 0, 'dismissed' => 1])
            ->assertJsonPath('data.it_coverage', ['with_responsible' => 1, 'without_responsible' => 1])
            ->assertJsonPath('data.users.total', 1)
            ->assertJsonStructure(['data' => ['profile' => ['name', 'roles'], 'recent_audit']]);
    }

    public function test_employee_gets_profile_only(): void
    {
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->a));

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonStructure(['data' => ['profile']])
            ->assertJsonMissingPath('data.employees')
            ->assertJsonMissingPath('data.organizations')
            ->assertJsonMissingPath('data.recent_audit');
    }

    public function test_organization_parameter_filters_to_subtree(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true));

        $this->getJson("/api/v1/dashboard?organization_id={$this->a->id}")->assertOk()
            ->assertJsonPath('data.organizations.total', 1)
            ->assertJsonPath('data.employees.total', 3);
    }

    public function test_organization_parameter_out_of_scope_is_rejected(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));

        $this->getJson("/api/v1/dashboard?organization_id={$this->b->id}")->assertStatus(422)
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_subsidiary_counts_stay_in_scope(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.organizations.total', 1)
            ->assertJsonPath('data.employees.total', 3);
    }
}
