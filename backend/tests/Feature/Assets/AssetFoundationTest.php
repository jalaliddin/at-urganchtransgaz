<?php

namespace Tests\Feature\Assets;

use App\Models\AssetCategory;
use App\Models\Role;
use App\Services\Support\NumberSequence;
use Database\Seeders\AssetsSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATIONS = ['view', 'create', 'update', 'assign', 'transfer', 'inventory', 'export'];

    /** @return array<string, array{string, list<string>}> */
    public static function defaults(): array
    {
        $all = ['view', 'create', 'update', 'delete', 'assign', 'transfer', 'inventory', 'dispose', 'export', 'manage_catalog'];

        return [
            'central head' => ['central-it-head', $all],
            'central staff' => ['central-it-staff', self::OPERATIONS],
            'org it' => ['org-it-responsible', self::OPERATIONS],
            'accountant' => ['accountant', ['view', 'inventory', 'export']],
            'org head' => ['org-head', ['view', 'export']],
            'auditor' => ['auditor', ['view', 'export']],
            'employee' => ['employee', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('defaults')]
    public function test_default_roles_get_asset_permissions(string $role, array $expected): void
    {
        $this->seed(RbacSeeder::class);

        $granted = Role::findByName($role, 'web')->permissions->pluck('name')
            ->filter(fn (string $p) => str_starts_with($p, 'assets.'))
            ->map(fn (string $p) => substr($p, 7))->sort()->values()->all();

        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_seeder_creates_spec_categories_idempotently_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();
        AssetCategory::where('code', 'printer')->update(['name' => 'Printerlar']);
        $this->seed(AssetsSeeder::class);

        $this->assertSame(
            ['server', 'computer', 'monitor', 'printer', 'network', 'telephony', 'power', 'av', 'network_material', 'other'],
            AssetCategory::orderBy('sort_order')->pluck('code')->all(),
        );
        $this->assertSame('Printerlar', AssetCategory::where('code', 'printer')->value('name'));
        $this->assertTrue(AssetCategory::where('code', 'network')->value('is_networked'));
        $this->assertFalse(AssetCategory::where('code', 'monitor')->value('is_networked'));
    }

    public function test_number_sequence_is_per_key_and_rolls_back_with_the_transaction(): void
    {
        $seq = app(NumberSequence::class);

        $this->assertSame(1, $seq->next('asset'));
        $this->assertSame(2, $seq->next('asset'));
        $this->assertSame(1, $seq->next('transfer:2026'));

        try {
            DB::transaction(function () use ($seq): void {
                $seq->next('asset');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(3, $seq->next('asset'));
    }
}
