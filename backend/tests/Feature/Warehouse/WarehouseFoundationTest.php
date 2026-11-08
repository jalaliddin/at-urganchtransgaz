<?php

namespace Tests\Feature\Warehouse;

use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Services\Settings\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RbacSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class WarehouseFoundationTest extends TestCase
{
    use CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    /** @return array<string, array{string, string, list<string>}> */
    public static function defaults(): array
    {
        $warehouseOps = ['view', 'receive', 'issue', 'transfer', 'inventory', 'export'];

        return [
            'central head warehouse' => ['central-it-head', 'warehouse', [...$warehouseOps, 'manage']],
            'central head requisitions' => ['central-it-head', 'requisitions', ['view', 'create', 'approve_org', 'review', 'approve', 'fulfill', 'export']],
            'central staff warehouse' => ['central-it-staff', 'warehouse', $warehouseOps],
            'central staff requisitions' => ['central-it-staff', 'requisitions', ['view', 'review', 'fulfill']],
            'org it warehouse' => ['org-it-responsible', 'warehouse', $warehouseOps],
            'org it requisitions' => ['org-it-responsible', 'requisitions', ['view', 'create']],
            'org head warehouse' => ['org-head', 'warehouse', ['view', 'export']],
            'org head requisitions' => ['org-head', 'requisitions', ['view', 'approve_org']],
            'accountant warehouse' => ['accountant', 'warehouse', ['view', 'receive', 'issue', 'inventory', 'export']],
            'accountant requisitions' => ['accountant', 'requisitions', ['view', 'fulfill']],
            'auditor warehouse' => ['auditor', 'warehouse', ['view', 'export']],
            'auditor requisitions' => ['auditor', 'requisitions', ['view', 'export']],
            'employee warehouse' => ['employee', 'warehouse', []],
            'employee requisitions' => ['employee', 'requisitions', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('defaults')]
    public function test_default_roles_get_module_permissions(string $role, string $module, array $expected): void
    {
        $this->seed(RbacSeeder::class);

        $granted = Role::findByName($role, 'web')->permissions->pluck('name')
            ->filter(fn (string $p) => str_starts_with($p, $module.'.'))
            ->map(fn (string $p) => substr($p, strlen($module) + 1))->sort()->values()->all();

        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_seeder_creates_units_and_spec_categories_idempotently_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();
        MaterialCategory::where('code', 'paper')->update(['name' => 'Qog\'oz (A4)']);
        $this->seed(WarehouseSeeder::class);

        $this->assertSame(
            ['cartridge', 'paper', 'peripheral', 'network', 'printer_part', 'other'],
            MaterialCategory::orderBy('sort_order')->pluck('code')->all(),
        );
        $this->assertSame('Qog\'oz (A4)', MaterialCategory::where('code', 'paper')->value('name'));
        $this->assertSame(['pcs', 'box', 'pack', 'm', 'kg', 'l', 'set'], Unit::orderBy('sort_order')->pluck('code')->all());
        $this->assertSame('dona', Unit::where('code', 'pcs')->value('name'));
    }

    public function test_database_refuses_a_negative_balance(): void
    {
        [$warehouse, $material] = $this->stockFixture();

        $this->expectException(QueryException::class);
        DB::table('stock_balances')->insert([
            'warehouse_id' => $warehouse->id, 'material_id' => $material->id, 'batch' => '',
            'quantity' => '-0.001', 'unit_cost' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_one_lot_per_warehouse_material_and_batch(): void
    {
        [$warehouse, $material] = $this->stockFixture();
        $row = ['warehouse_id' => $warehouse->id, 'material_id' => $material->id, 'batch' => '', 'quantity' => 1, 'unit_cost' => 0];
        DB::table('stock_balances')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('stock_balances')->insert($row);
    }

    public function test_head_approval_setting_defaults_to_on(): void
    {
        $this->assertTrue(app(SettingsService::class)->get('requisitions.head_approval'));
    }
}
