<?php

namespace App\Services\Auth;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session-based authentication flows for the SPA.
 */
class AuthService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SessionRevoker $sessions,
    ) {}

    /**
     * @throws ValidationException on bad credentials or inactive account
     */
    public function login(Request $request, string $username, string $password, bool $remember): User
    {
        $username = Str::lower(trim($username));
        $user = User::withoutGlobalScopes()->where('username', $username)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->audit->record(AuditEvent::LoginFailed, $user, userLabel: Str::limit($username, 64, ''), actor: $user);
            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            $this->audit->record(AuditEvent::LoginFailed, $user, new: ['reason' => 'inactive'], actor: $user);
            throw ValidationException::withMessages(['username' => __('auth.inactive')]);
        }

        Auth::guard('web')->login($user, $remember);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        $this->audit->record(AuditEvent::Login, $user, actor: $user);

        return $user;
    }

    public function logout(Request $request, User $user): void
    {
        $this->audit->record(AuditEvent::Logout, $user, actor: $user);

        Auth::guard('web')->logout();
        Auth::forgetGuards(); // drop the user cached by the sanctum request guard
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    public function changePassword(Request $request, User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword): void {
            $user->forceFill([
                'password' => $newPassword,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->saveQuietly();

            $this->audit->record(AuditEvent::PasswordChanged, $user, actor: $user);
        });

        $this->sessions->revokeOthers($user, $request->hasSession() ? $request->session()->getId() : null);
    }

    /**
     * Sends a reset link when $login (username or email) belongs to an active user
     * with an email. Never reveals whether the account exists.
     */
    public function sendResetLink(string $login): void
    {
        $login = trim($login);
        $user = User::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('username', Str::lower($login))->orWhere('email', $login))
            ->first();

        if ($user === null || ! $user->is_active || $user->email === null) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $user->email]);
    }

    /**
     * @param  array{token: string, email: string, password: string, password_confirmation: string}  $credentials
     *
     * @throws ValidationException when the token is invalid or expired
     */
    public function resetPassword(array $credentials): void
    {
        $status = Password::broker()->reset($credentials, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->saveQuietly();

                $this->audit->record(AuditEvent::PasswordReset, $user, actor: $user);
            });

            $this->sessions->revokeAll($user);
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __((string) $status)]);
        }
    }
}
