<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class MeTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_me_lists_roles_permissions_and_access(): void
    {
        $org = Organization::factory()->create(['name' => 'Xorazm GTS']);
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $org));

        $response = $this->getJson('/api/v1/auth/me')->assertOk();

        $this->assertContains('employees.update', $response->json('data.permissions'));
        $this->assertNotContains('users.view', $response->json('data.permissions'));
        $response->assertJsonPath('data.roles.0.name', Roles::ORG_IT_RESPONSIBLE)
            ->assertJsonPath('data.access.0.organization_id', $org->id)
            ->assertJsonPath('data.access.0.organization_name', 'Xorazm GTS')
            ->assertJsonPath('data.organization.name', 'Xorazm GTS')
            ->assertJsonPath('data.is_super_admin', false)
            ->assertJsonMissingPath('data.password');
    }

    public function test_super_admin_has_every_permission(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', true)
            ->assertJsonPath('data.permissions', app(PermissionRegistry::class)->all());
    }

    public function test_patch_me_updates_locale(): void
    {
        $user = $this->makeUser(Roles::EMPLOYEE, Organization::factory()->create());
        $this->actingAs($user);

        $this->patchJson('/api/v1/auth/me', ['locale' => 'ru'])->assertOk()->assertJsonPath('data.locale', 'ru');
        $this->patchJson('/api/v1/auth/me', ['locale' => 'en'])->assertStatus(422)->assertJsonValidationErrors(['locale']);
        $this->assertSame('ru', $user->fresh()->locale);
    }
}
