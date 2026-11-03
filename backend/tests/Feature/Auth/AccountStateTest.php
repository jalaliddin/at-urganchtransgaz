<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AccountStateTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->withHeader('Referer', 'http://localhost');
        $this->org = Organization::factory()->create();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->makeUser(Roles::EMPLOYEE, $this->org);
        $user->update(['username' => 'off.user', 'password' => 'Secret12345', 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['username' => 'off.user', 'password' => 'Secret12345'])
            ->assertStatus(422)
            ->assertJsonPath('errors.username.0', __('auth.inactive'));
        $this->assertGuest();
    }

    public function test_deactivated_user_gets_401_on_next_request(): void
    {
        $user = $this->makeUser(Roles::EMPLOYEE, $this->org);
        $this->actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertOk();

        $user->update(['is_active' => false]);

        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_must_change_password_blocks_everything_else(): void
    {
        $user = $this->makeUser(Roles::SUPER_ADMIN);
        $user->update(['must_change_password' => true]);
        $this->actingAs($user);

        $this->getJson('/api/v1/settings')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.must_change_password', true);
        $this->patchJson('/api/v1/auth/me', ['locale' => 'ru'])->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
    }

    public function test_expired_password_forces_change(): void
    {
        app(SettingsService::class)->update(['security.password_expires_days' => 30]);
        $user = $this->makeUser(Roles::SUPER_ADMIN);
        $user->forceFill(['password_changed_at' => now()->subDays(31)])->save();
        $this->actingAs($user);

        $this->getJson('/api/v1/settings')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.password_expired', true);
    }
}
