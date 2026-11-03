<?php

namespace Tests\Feature\Access;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class OrganizationScopeTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $a;

    private Organization $b;

    protected function setUp(): void
    {
        parent::setUp();
        $root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($root)->create();
        $this->b = Organization::factory()->childOf($root)->create();
        Employee::factory()->count(3)->for($this->a)->create();
        Employee::factory()->count(2)->for($this->b)->create();
    }

    public function test_subsidiary_user_sees_only_own_employees(): void
    {
        $other = Employee::where('organization_id', $this->b->id)->first();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a));

        $this->assertSame(3, Employee::count());
        $this->assertNull(Employee::find($other->id));
    }

    public function test_unauthenticated_context_is_unscoped(): void
    {
        $this->assertSame(5, Employee::count());
    }

    public function test_super_admin_sees_all(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->assertSame(5, Employee::count());
    }

    public function test_users_without_organization_hidden_from_non_super_admin(): void
    {
        $system = User::factory()->create(['organization_id' => null]);
        $viewer = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a);
        $this->actingAs($viewer);

        $this->assertNull(User::find($system->id));
        $this->assertNotNull(User::find($viewer->id));
    }

    public function test_organization_model_is_scoped_by_its_own_id(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a));

        $this->assertSame([$this->a->id], Organization::pluck('id')->all());
    }

    public function test_within_organization_subtree_scope(): void
    {
        $a1 = Organization::factory()->childOf($this->a)->create();
        Employee::factory()->for($a1)->create();

        $this->assertSame(4, Employee::withinOrganizationSubtree($this->a->id)->count());
        $this->assertSame(0, Employee::withinOrganizationSubtree(999999)->count());
    }
}
