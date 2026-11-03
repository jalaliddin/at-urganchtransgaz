<?php

namespace Tests\Feature\Users;

use App\Exceptions\DomainConflictException;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccessChanged;
use App\Services\Users\AccessManager;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AccessManagerTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $b;

    private User $head;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($this->root)->create();
        $this->b = Organization::factory()->childOf($this->root)->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true);
        $this->target = $this->makeUser(Roles::EMPLOYEE, $this->a);
    }

    private function roles(User $user, array $roles): TestResponse
    {
        return $this->putJson("/api/v1/users/{$user->id}/roles", ['roles' => $roles]);
    }

    private function access(User $user, array $access): TestResponse
    {
        return $this->putJson("/api/v1/users/{$user->id}/organization-access", ['access' => $access]);
    }

    public function test_head_cannot_grant_super_admin(): void
    {
        $this->actingAs($this->head);

        $this->roles($this->target, [Roles::SUPER_ADMIN])->assertForbidden();
    }

    public function test_head_cannot_grant_role_with_permissions_he_lacks(): void
    {
        $powerful = Role::create(['name' => 'role-admins', 'guard_name' => 'web', 'display_name' => 'Role admins']);
        $powerful->givePermissionTo('roles.manage');
        $this->actingAs($this->head);

        $this->roles($this->target, [$powerful->name])->assertForbidden();
    }

    public function test_head_cannot_strip_roles_he_could_not_grant(): void
    {
        $superTwo = $this->makeUser(Roles::SUPER_ADMIN);
        $superTwo->update(['organization_id' => $this->a->id]);
        $this->grantAccess($superTwo, $this->a);
        $this->actingAs($this->head);

        $this->roles($superTwo, [Roles::EMPLOYEE])->assertForbidden();
    }

    public function test_head_grants_role_and_target_is_notified_and_audited(): void
    {
        Notification::fake();
        $this->actingAs($this->head);

        $this->roles($this->target, [Roles::ORG_IT_RESPONSIBLE])->assertOk()
            ->assertJsonPath('data.roles.0.name', Roles::ORG_IT_RESPONSIBLE);

        Notification::assertSentTo($this->target, AccessChanged::class);
        $log = AuditLog::where('event', 'roles_changed')->latest('id')->first();
        $this->assertSame(['roles' => [Roles::EMPLOYEE]], $log->old_values);
        $this->assertSame(['roles' => [Roles::ORG_IT_RESPONSIBLE]], $log->new_values);
    }

    public function test_access_outside_actor_scope_is_rejected(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a, true));

        $this->access($this->target, [['organization_id' => $this->b->id, 'include_descendants' => false]])
            ->assertStatus(422)->assertJsonValidationErrors(['access.0.organization_id']);
    }

    public function test_include_descendants_requires_subtree_cover(): void
    {
        $a1 = Organization::factory()->childOf($this->a)->create();
        $partial = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a); // A only, not its child
        $this->actingAs($partial);

        $this->access($this->target, [['organization_id' => $this->a->id, 'include_descendants' => true]])
            ->assertStatus(422)->assertJsonValidationErrors(['access.0.include_descendants']);
        $this->assertNotNull($a1);
    }

    public function test_cannot_change_own_roles_or_access(): void
    {
        $this->actingAs($this->head);

        $this->roles($this->head, [Roles::CENTRAL_IT_HEAD, Roles::AUDITOR])->assertForbidden();
        $this->access($this->head, [['organization_id' => $this->root->id, 'include_descendants' => true]])->assertForbidden();

        $super = $this->makeUser(Roles::SUPER_ADMIN);
        $this->actingAs($super);
        $this->roles($super, [Roles::EMPLOYEE])->assertForbidden();
    }

    public function test_access_change_takes_effect_and_is_audited(): void
    {
        $this->actingAs($this->head);

        $this->access($this->target, [['organization_id' => $this->b->id, 'include_descendants' => true]])->assertOk();

        $this->assertTrue($this->target->fresh()->canAccessOrganization($this->b->id));
        $this->assertFalse($this->target->fresh()->canAccessOrganization($this->a->id));
        $this->assertSame(1, AuditLog::where('event', 'access_changed')->where('auditable_id', $this->target->id)->count());
    }

    public function test_user_with_access_beyond_the_actors_scope_cannot_be_managed(): void
    {
        $outside = Organization::factory()->create(); // another tree the head cannot see
        $this->grantAccess($this->target, $outside);
        $this->actingAs($this->head);

        $this->access($this->target, [['organization_id' => $this->b->id, 'include_descendants' => true]])->assertForbidden();

        $this->assertTrue($this->target->fresh()->canAccessOrganization($outside->id));
        $this->assertTrue($this->target->fresh()->canAccessOrganization($this->a->id));
    }

    public function test_last_active_super_admin_is_protected_for_system_callers(): void
    {
        $only = $this->makeUser(Roles::SUPER_ADMIN);

        $this->expectException(DomainConflictException::class);
        app(AccessManager::class)->syncRoles($only, [Roles::EMPLOYEE], null);
    }
}
