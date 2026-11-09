<?php

namespace Tests\Feature\Printers;

use App\Models\AssetCategory;
use App\Models\Organization;
use App\Models\Printer;
use App\Models\PrinterCounter;
use App\Models\Role;
use App\Support\Permissions\Roles;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesPrinters;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class PrinterFoundationTest extends TestCase
{
    use CreatesPrinters, InteractsWithAccess, RefreshDatabase;

    /** @return array<string, array{string, list<string>}> */
    public static function defaults(): array
    {
        return [
            'central head' => ['central-it-head', ['view', 'service', 'manage', 'export']],
            'central staff' => ['central-it-staff', ['view', 'service', 'export']],
            'org it' => ['org-it-responsible', ['view', 'service', 'export']],
            'org head' => ['org-head', ['view', 'export']],
            'accountant' => ['accountant', ['view', 'export']],
            'auditor' => ['auditor', ['view', 'export']],
            'employee' => ['employee', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('defaults')]
    public function test_default_roles_get_printer_permissions(string $role, array $expected): void
    {
        $this->seed(RbacSeeder::class);
        $granted = Role::findByName($role, 'web')->permissions->pluck('name')
            ->filter(fn (string $p) => str_starts_with($p, 'printers.'))->map(fn (string $p) => substr($p, 9))->sort()->values()->all();
        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_an_asset_with_printer_history_cannot_be_deleted_but_a_bare_profile_goes_with_it(): void
    {
        $this->seedPrinters();
        $this->assertSame('printer', AssetCategory::PRINTER);
        $organization = Organization::factory()->create();
        $head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $organization, true);
        $used = $this->printerIn($organization, $head);
        PrinterCounter::query()->create(['organization_id' => $organization->id, 'asset_id' => $used->id, 'read_at' => '2026-11-01',
            'total' => 1200, 'source' => 'manual', 'created_by' => $head->id]);
        $bare = $this->printerIn($organization, $head);
        Printer::query()->create(['asset_id' => $bare->id, 'connection_type' => 'usb']);

        $this->actingAs($head)->deleteJson("/api/v1/assets/{$used->id}")->assertStatus(409);
        $this->actingAs($head)->deleteJson("/api/v1/assets/{$bare->id}")->assertOk();
        $this->assertDatabaseMissing('printers', ['asset_id' => $bare->id]);
    }
}
