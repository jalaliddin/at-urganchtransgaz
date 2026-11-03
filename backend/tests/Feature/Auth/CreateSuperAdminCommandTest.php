<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private function configureEnv(?string $password = 'Admin12345'): void
    {
        config(['app.super_admin' => [
            'username' => 'admin', 'password' => $password, 'name' => 'Tizim administratori', 'email' => null,
        ]]);
    }

    public function test_from_env_creates_super_admin_once(): void
    {
        $this->configureEnv();

        $this->artisan('app:create-super-admin', ['--from-env' => true])->assertSuccessful();
        $this->artisan('app:create-super-admin', ['--from-env' => true])->assertSuccessful();

        $users = User::where('username', 'admin')->get();
        $this->assertCount(1, $users);
        $this->assertTrue($users[0]->hasRole(Roles::SUPER_ADMIN));
        $this->assertTrue($users[0]->must_change_password);
        $this->assertNull($users[0]->organization_id);
        $this->assertTrue(Hash::check('Admin12345', $users[0]->password));
    }

    public function test_from_env_fails_without_password(): void
    {
        $this->configureEnv(null);

        $this->artisan('app:create-super-admin', ['--from-env' => true])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->configureEnv('short');

        $this->artisan('app:create-super-admin', ['--from-env' => true])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_interactive_mode(): void
    {
        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Login (username)', 'root.admin')
            ->expectsQuestion('F.I.Sh.', 'Bosh administrator')
            ->expectsQuestion('Email (ixtiyoriy)', '')
            ->expectsQuestion('Parol', 'Admin12345')
            ->expectsQuestion('Parolni tasdiqlang', 'Admin12345')
            ->assertSuccessful();

        $this->assertTrue(User::where('username', 'root.admin')->first()->hasRole(Roles::SUPER_ADMIN));
    }
}
