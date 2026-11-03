<?php

namespace Tests\Feature\Users;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserOrganizationAccess;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class UserApiTest extends TestCase
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

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'a.karimov',
            'name' => 'Karimov Aziz',
            'email' => 'a.karimov@urtg.uz',
            'organization_id' => $this->a->id,
            'roles' => [Roles::ORG_IT_RESPONSIBLE],
        ], $overrides);
    }

    public function test_create_returns_temporary_password_once(): void
    {
        $this->actingAs($this->head);

        $response = $this->postJson('/api/v1/users', $this->payload())->assertCreated()
            ->assertJsonPath('data.roles.0.name', Roles::ORG_IT_RESPONSIBLE)
            ->assertJsonPath('data.must_change_password', true);
        $temporary = $response->json('meta.temporary_password');
        $id = $response->json('data.id');

        $this->assertSame(14, strlen($temporary));
        $this->getJson("/api/v1/users/{$id}")->assertOk()->assertJsonMissingPath('meta.temporary_password');
        $this->assertSame(
            [['organization_id' => $this->a->id, 'include_descendants' => false]],
            UserOrganizationAccess::where('user_id', $id)->get(['organization_id', 'include_descendants'])->toArray(),
        );
    }

    public function test_temporary_password_logs_in_and_must_be_changed(): void
    {
        $this->actingAs($this->head);
        $temporary = $this->postJson('/api/v1/users', $this->payload())->json('meta.temporary_password');
        auth()->forgetGuards();

        $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/v1/auth/login', ['username' => 'a.karimov', 'password' => $temporary])
            ->assertOk()->assertJsonPath('data.must_change_password', true);
        $this->getJson('/api/v1/organizations')->assertForbidden()->assertJsonPath('code', 'password_change_required');
    }

    public function test_username_format_and_uniqueness_including_deleted(): void
    {
        User::factory()->create(['username' => 'taken'])->delete();
        $this->actingAs($this->head);

        $this->postJson('/api/v1/users', $this->payload(['username' => 'Bad Name']))->assertJsonValidationErrors(['username']);
        $this->postJson('/api/v1/users', $this->payload(['username' => 'taken']))->assertJsonValidationErrors(['username']);
    }

    public function test_employee_link_rules(): void
    {
        $employee = Employee::factory()->for($this->a)->create();
        User::factory()->forEmployee($employee)->create();
        $free = Employee::factory()->for($this->a)->create();
        $this->actingAs($this->head);

        $this->postJson('/api/v1/users', $this->payload(['employee_id' => $employee->id]))
            ->assertJsonValidationErrors(['employee_id']);
        $this->postJson('/api/v1/users', $this->payload(['employee_id' => $free->id, 'organization_id' => $this->b->id]))
            ->assertJsonValidationErrors(['organization_id']);
        $this->postJson('/api/v1/users', $this->payload(['employee_id' => $free->id, 'organization_id' => null]))
            ->assertCreated()->assertJsonPath('data.organization.id', $this->a->id)
            ->assertJsonPath('data.employee.id', $free->id);
    }

    public function test_non_super_admin_must_set_organization(): void
    {
        $this->actingAs($this->head);

        $this->postJson('/api/v1/users', $this->payload(['organization_id' => null]))->assertJsonValidationErrors(['organization_id']);
    }

    public function test_list_is_scoped(): void
    {
        $inA = $this->makeUser(Roles::EMPLOYEE, $this->a);
        $system = $this->makeUser(Roles::SUPER_ADMIN);
        $this->actingAs($this->head);

        $ids = collect($this->getJson('/api/v1/users')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($inA->id));
        $this->assertFalse($ids->contains($system->id));
        $this->getJson("/api/v1/users/{$system->id}")->assertNotFound();

        $this->actingAs($system);
        $this->assertTrue(collect($this->getJson('/api/v1/users?per_page=100')->json('data'))->pluck('id')->contains($system->id));
    }

    public function test_filters_and_search(): void
    {
        $this->makeUser(Roles::AUDITOR, $this->b)->update(['username' => 'audit.b']);
        $this->actingAs($this->head);

        $this->assertSame(['audit.b'], collect($this->getJson('/api/v1/users?filter[role]=auditor')->json('data'))->pluck('username')->all());
        $this->assertSame(['audit.b'], collect($this->getJson('/api/v1/users?search=audit.b')->json('data'))->pluck('username')->all());
    }

    public function test_org_head_without_users_view_is_forbidden(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_update_profile_fields(): void
    {
        $target = $this->makeUser(Roles::EMPLOYEE, $this->a);
        $this->actingAs($this->head);

        $this->putJson("/api/v1/users/{$target->id}", ['name' => 'Yangi Ism', 'email' => 'new@urtg.uz', 'locale' => 'ru'])
            ->assertOk()->assertJsonPath('data.name', 'Yangi Ism')->assertJsonPath('data.locale', 'ru');
    }
}
