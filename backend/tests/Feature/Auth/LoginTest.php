<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->withHeader('Referer', 'http://localhost');
        $this->user = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create());
        $this->user->update(['username' => 'a.karimov', 'password' => 'Secret12345']);
    }

    private function login(string $username = 'a.karimov', string $password = 'Secret12345'): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['username' => $username, 'password' => $password]);
    }

    public function test_login_success_returns_me_and_is_audited(): void
    {
        $this->login()->assertOk()->assertJsonPath('data.username', 'a.karimov');

        $this->assertAuthenticatedAs($this->user);
        $this->assertNotNull($this->user->fresh()->last_login_at);
        $this->assertSame(1, AuditLog::where('event', 'login')->where('user_id', $this->user->id)->count());
    }

    public function test_wrong_password_is_rejected_generically_and_audited(): void
    {
        $this->login(password: 'WrongPass1')
            ->assertStatus(422)
            ->assertJsonPath('errors.username.0', __('auth.failed'));

        $log = AuditLog::where('event', 'login_failed')->latest('id')->first();
        $this->assertSame('a.karimov', $log->user_label);
        $this->assertStringNotContainsString('WrongPass1', json_encode(AuditLog::all()->toArray()));
        $this->assertGuest();
    }

    public function test_unknown_username_gets_the_same_response(): void
    {
        $this->login('nobody', 'WrongPass1')
            ->assertStatus(422)
            ->assertJsonPath('errors.username.0', __('auth.failed'));
    }

    public function test_sixth_attempt_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login(password: 'WrongPass1')->assertStatus(422);
        }

        $this->login(password: 'WrongPass1')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_logout_ends_session_and_is_audited(): void
    {
        $this->login()->assertOk();

        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertSame(1, AuditLog::where('event', 'logout')->where('user_id', $this->user->id)->count());
    }
}
