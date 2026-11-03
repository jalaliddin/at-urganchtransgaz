<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\SessionRevoker;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->user = $this->makeUser(Roles::EMPLOYEE, Organization::factory()->create());
        $this->user->update(['password' => 'OldSecret123', 'must_change_password' => true]);
        $this->actingAs($this->user);
    }

    private function change(string $current, string $new, ?string $confirmation = null): TestResponse
    {
        return $this->putJson('/api/v1/auth/password', [
            'current_password' => $current,
            'password' => $new,
            'password_confirmation' => $confirmation ?? $new,
        ]);
    }

    private function addSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $userId, 'ip_address' => '10.0.0.1',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
        ]);
    }

    public function test_requires_correct_current_password(): void
    {
        $this->change('nope', 'NewSecret123')->assertStatus(422)->assertJsonValidationErrors(['current_password']);
    }

    public function test_respects_min_length_setting(): void
    {
        app(SettingsService::class)->update(['security.password_min_length' => 12]);

        $this->change('OldSecret123', 'Abc123def45')->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->change('OldSecret123', 'Abc123def456')->assertOk();
    }

    public function test_requires_letters_and_numbers(): void
    {
        $this->change('OldSecret123', 'onlyletterspassword')->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->change('OldSecret123', '12345678901234')->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_new_password_must_differ_and_be_confirmed(): void
    {
        $this->change('OldSecret123', 'OldSecret123')->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->change('OldSecret123', 'NewSecret123', 'Mismatch123')->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_success_updates_flags_revokes_other_sessions_and_audits(): void
    {
        $this->addSession('other-session-1', $this->user->id);
        $this->addSession('other-session-2', $this->user->id);
        $stranger = User::factory()->create();
        $this->addSession('stranger-session', $stranger->id);

        $this->change('OldSecret123', 'NewSecret123')->assertOk();

        $fresh = $this->user->fresh();
        $this->assertTrue(Hash::check('NewSecret123', $fresh->password));
        $this->assertFalse($fresh->must_change_password);
        $this->assertNotNull($fresh->password_changed_at);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $stranger->id)->count());
        $this->assertSame(1, AuditLog::where('event', 'password_changed')->count());
    }

    public function test_revoke_others_keeps_the_current_session(): void
    {
        $this->addSession('current', $this->user->id);
        $this->addSession('other', $this->user->id);

        app(SessionRevoker::class)->revokeOthers($this->user, 'current');

        $this->assertSame(['current'], DB::table('sessions')->where('user_id', $this->user->id)->pluck('id')->all());
    }
}
