<?php

namespace Tests\Feature\Rbac;

use App\Models\Role;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HelpdeskPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, list<string>}> */
    public static function defaults(): array
    {
        return [
            'employee' => ['employee', ['helpdesk.create']],
            'org head' => ['org-head', ['helpdesk.create', 'helpdesk.view', 'helpdesk.export']],
            'org it' => ['org-it-responsible', ['helpdesk.create', 'helpdesk.view', 'helpdesk.process', 'helpdesk.assign', 'helpdesk.export', 'documents.view', 'documents.manage']],
            'central staff' => ['central-it-staff', ['helpdesk.create', 'helpdesk.view', 'helpdesk.process', 'helpdesk.export', 'documents.view', 'documents.manage']],
            'central head' => ['central-it-head', ['helpdesk.create', 'helpdesk.view', 'helpdesk.process', 'helpdesk.assign', 'helpdesk.export', 'helpdesk.manage', 'documents.view', 'documents.manage']],
            'accountant' => ['accountant', ['helpdesk.create', 'documents.view', 'documents.manage']],
            'auditor' => ['auditor', ['helpdesk.view', 'helpdesk.export', 'documents.view']],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('defaults')]
    public function test_default_role_gets_phase2_permissions(string $role, array $expected): void
    {
        $this->seed(RbacSeeder::class);

        $granted = Role::findByName($role, 'web')->permissions->pluck('name')
            ->filter(fn (string $p) => str_starts_with($p, 'helpdesk.') || str_starts_with($p, 'documents.'))
            ->sort()->values()->all();

        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_reseed_grants_new_permissions_without_restoring_removed_ones(): void
    {
        $this->seed(RbacSeeder::class);
        $role = Role::findByName('org-head', 'web');
        $role->revokePermissionTo('helpdesk.export');
        Permission::where('name', 'helpdesk.view')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(RbacSeeder::class);

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('helpdesk.view'));
        $this->assertFalse($role->hasPermissionTo('helpdesk.export'));
    }
}
