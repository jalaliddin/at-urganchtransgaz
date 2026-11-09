<?php

namespace Tests\Feature\Tasks;

use App\Models\Role;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, list<string>}> */
    public static function defaults(): array
    {
        return [
            'central head' => ['central-it-head', ['view', 'create', 'manage', 'workload', 'export']],
            'central staff' => ['central-it-staff', ['view', 'create', 'export']],
            'org it' => ['org-it-responsible', ['view', 'create', 'manage', 'export']],
            'org head' => ['org-head', ['view']],
            'auditor' => ['auditor', ['view', 'workload', 'export']],
            'accountant' => ['accountant', []],
            'employee' => ['employee', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('defaults')]
    public function test_default_roles_get_task_permissions(string $role, array $expected): void
    {
        $this->seed(RbacSeeder::class);
        $granted = Role::findByName($role, 'web')->permissions->pluck('name')
            ->filter(fn (string $p) => str_starts_with($p, 'tasks.'))->map(fn (string $p) => substr($p, 6))->sort()->values()->all();
        sort($expected);
        $this->assertSame($expected, $granted);
    }
}
