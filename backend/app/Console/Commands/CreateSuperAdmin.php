<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\PasswordRules;
use App\Support\Permissions\Roles;
use Database\Seeders\RbacSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates the first Super Admin safely: no default password in code, password
 * change forced on first login. Idempotent: an existing username only gets the role.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin
        {--from-env : Read SUPER_ADMIN_USERNAME/PASSWORD/NAME/EMAIL instead of prompting}';

    protected $description = 'Create (or confirm) the first Super Admin account';

    public function handle(PasswordRules $passwordRules): int
    {
        if (! Role::query()->where('name', Roles::SUPER_ADMIN)->exists()) {
            $this->callSilently('db:seed', ['--class' => RbacSeeder::class, '--force' => true]);
        }

        $input = $this->option('from-env') ? $this->fromConfig() : $this->fromPrompts();

        $existing = User::withoutGlobalScopes()->withTrashed()->where('username', $input['username'])->first();
        if ($existing !== null) {
            $existing->assignRole(Roles::SUPER_ADMIN);
            $this->components->info("User [{$existing->username}] already exists; Super Admin role ensured. Password unchanged.");

            return self::SUCCESS;
        }

        $validator = Validator::make($input, [
            'username' => ['required', 'string', 'regex:/^[a-z0-9._-]{3,64}$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', $passwordRules->make()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($input): void {
            $user = User::create([
                'username' => $input['username'],
                'name' => $input['name'],
                'email' => $input['email'] ?: null,
                'password' => $input['password'],
                'organization_id' => null,
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $user->assignRole(Roles::SUPER_ADMIN);
        });

        $this->components->info("Super Admin [{$input['username']}] created. The password must be changed at first login.");

        return self::SUCCESS;
    }

    /** @return array{username: string, name: string, email: string|null, password: string|null} */
    private function fromConfig(): array
    {
        $config = (array) config('app.super_admin', []);
        $username = Str::lower(trim((string) ($config['username'] ?? '')));

        return [
            'username' => $username,
            'name' => (string) ($config['name'] ?? '') ?: 'Super Admin',
            'email' => ($config['email'] ?? null) ?: null,
            'password' => ($config['password'] ?? null) ?: null,
        ];
    }

    /** @return array{username: string, name: string, email: string|null, password: string|null} */
    private function fromPrompts(): array
    {
        $username = Str::lower(trim(text('Login (username)', required: true)));
        $name = text('F.I.Sh.', default: 'Super Admin', required: true);
        $email = text('Email (ixtiyoriy)');
        $password = password('Parol', required: true);
        $confirmation = password('Parolni tasdiqlang', required: true);

        if ($password !== $confirmation) {
            $this->components->error('Parollar mos kelmadi.');
            $password = null;
        }

        return ['username' => $username, 'name' => $name, 'email' => $email ?: null, 'password' => $password];
    }
}
