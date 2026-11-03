<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Referer', 'http://localhost');
    }

    public function test_forgot_password_answers_identically_for_known_and_unknown(): void
    {
        Notification::fake();
        $user = User::factory()->create(['username' => 'a.karimov', 'email' => 'a.karimov@urtg.uz']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['login' => 'a.karimov'])->assertOk();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['login' => 'nobody'])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTo($user, ResetPasswordLink::class);
        Notification::assertSentTimes(ResetPasswordLink::class, 1);
    }

    public function test_forgot_password_accepts_email_as_login(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'boss@urtg.uz']);

        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'boss@urtg.uz'])->assertOk();

        Notification::assertSentTo($user, ResetPasswordLink::class);
    }

    public function test_user_without_email_or_inactive_gets_no_mail(): void
    {
        Notification::fake();
        User::factory()->create(['username' => 'no.mail', 'email' => null]);
        User::factory()->inactive()->create(['username' => 'off.user', 'email' => 'off@urtg.uz']);

        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'no.mail'])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'off.user'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_reset_with_valid_token(): void
    {
        $user = User::factory()->mustChangePassword()->create(['email' => 'a@urtg.uz']);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token, 'email' => 'a@urtg.uz',
            'password' => 'BrandNew123', 'password_confirmation' => 'BrandNew123',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('BrandNew123', $fresh->password));
        $this->assertFalse($fresh->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, AuditLog::where('event', 'password_reset')->where('auditable_id', $user->id)->count());
    }

    public function test_reset_with_invalid_token_fails(): void
    {
        User::factory()->create(['email' => 'a@urtg.uz']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'bogus', 'email' => 'a@urtg.uz',
            'password' => 'BrandNew123', 'password_confirmation' => 'BrandNew123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_reset_link_points_to_the_spa(): void
    {
        $user = User::factory()->create(['email' => 'a@urtg.uz']);

        $mail = (new ResetPasswordLink('tok123'))->toMail($user);

        $this->assertStringStartsWith(config('app.url').'/reset-password?token=tok123&email=a%40urtg.uz', $mail->actionUrl);
    }
}
