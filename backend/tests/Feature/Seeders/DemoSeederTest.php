<?php

namespace Tests\Feature\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\Roles;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_idempotent(): void
    {
        config(['app.demo_password' => 'Demo12345pass']);
        $this->seed(RbacSeeder::class);

        $this->seed(DemoSeeder::class);
        $counts = [Organization::count(), User::count()];
        $this->seed(DemoSeeder::class);

        $this->assertSame($counts, [Organization::count(), User::count()]);
        $this->assertGreaterThanOrEqual(4, $counts[0]);
        $head = User::where('username', 'demo.head')->first();
        $this->assertTrue($head->hasRole(Roles::CENTRAL_IT_HEAD));
        $this->assertTrue(Hash::check('Demo12345pass', $head->password));
        foreach (Roles::ALL as $role) {
            $this->assertTrue(User::role($role)->exists(), "no demo user for {$role}");
        }
    }

    public function test_database_seeder_skips_demo_outside_development(): void
    {
        config(['app.demo_password' => 'Demo12345pass']);
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Organization::count());
        $this->assertNotNull(Role::findByName(Roles::SUPER_ADMIN));
    }

    public function test_demo_seed_requires_password(): void
    {
        config(['app.demo_password' => null]);
        $this->seed(RbacSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->seed(DemoSeeder::class);
    }
}
