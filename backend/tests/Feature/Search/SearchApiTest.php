<?php

namespace Tests\Feature\Search;

use App\Models\Employee;
use App\Models\Organization;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $a;

    private Organization $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($root)->create(['name' => 'Xorazm GTS']);
        $this->b = Organization::factory()->childOf($root)->create(['name' => 'Xorazm MGQB']);
    }

    public function test_results_respect_scope_and_permissions(): void
    {
        $mine = Employee::factory()->for($this->a)->create(['last_name' => 'Rahimov']);
        Employee::factory()->for($this->b)->create(['last_name' => 'Rahimova']);
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));

        $this->getJson('/api/v1/search?q=rahim')->assertOk()
            ->assertJsonCount(1, 'data.employees')
            ->assertJsonPath('data.employees.0.id', $mine->id)
            ->assertJsonMissingPath('data.users');
        $this->getJson('/api/v1/search?q=xorazm')->assertJsonCount(1, 'data.organizations');
    }

    public function test_employee_role_gets_no_groups(): void
    {
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->a));

        $this->getJson('/api/v1/search?q=xorazm')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_query_too_short(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));

        $this->getJson('/api/v1/search?q=x')->assertStatus(422)->assertJsonValidationErrors(['q']);
    }

    public function test_apostrophe_variants_match(): void
    {
        $employee = Employee::factory()->for($this->a)->create(['last_name' => 'Toʻxtayev']);
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));

        $this->getJson("/api/v1/search?q=To'xta")->assertOk()->assertJsonPath('data.employees.0.id', $employee->id);
    }
}
