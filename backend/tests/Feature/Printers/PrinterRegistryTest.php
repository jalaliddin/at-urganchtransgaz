<?php

namespace Tests\Feature\Printers;

use App\Enums\AssetStatus;
use App\Models\AssetMaintenance;
use App\Models\AssetModel;
use App\Models\Organization;
use App\Models\Printer;
use App\Models\PrinterCounter;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPrinters;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class PrinterRegistryTest extends TestCase
{
    use CreatesPrinters, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $it;

    private AssetModel $m404;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPrinters();
        $this->center = Organization::factory()->create(['name' => 'Urganchtransgaz']);
        $this->branch = Organization::factory()->create(['name' => 'Xiva GTS', 'parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->m404 = $this->printerModel();
    }

    public function test_the_registry_lists_printers_in_scope_with_profile_and_last_counter(): void
    {
        $branchPrinter = $this->printerIn($this->branch, $this->head, ['model_id' => $this->m404->id, 'manufacturer_id' => $this->m404->manufacturer_id, 'ip_address' => '10.10.1.25']);
        Printer::query()->create(['asset_id' => $branchPrinter->id, 'connection_type' => 'network', 'is_color' => false]);
        PrinterCounter::query()->create(['organization_id' => $this->branch->id, 'asset_id' => $branchPrinter->id, 'read_at' => '2026-10-01', 'total' => 1000, 'source' => 'manual']);
        PrinterCounter::query()->create(['organization_id' => $this->branch->id, 'asset_id' => $branchPrinter->id, 'read_at' => '2026-11-01', 'total' => 1800, 'source' => 'manual']);
        $centerPrinter = $this->printerIn($this->center, $this->head);
        $foreign = $this->printerIn(Organization::factory()->create(), $this->head);
        $computer = $this->assetIn($this->branch, $this->head);
        $fits = $this->cartridge([], [$this->m404]);

        $this->actingAs($this->it)->getJson('/api/v1/printers')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $branchPrinter->id)->assertJsonPath('data.0.connection_type', 'network')
            ->assertJsonPath('data.0.last_counter.total', 1800)->assertJsonPath('data.0.last_counter.read_at', '2026-11-01')
            ->assertJsonPath('data.0.model.id', $this->m404->id)->assertJsonPath('data.0.ip_address', '10.10.1.25');
        $this->getJson("/api/v1/printers/{$computer->id}")->assertNotFound();
        $this->getJson("/api/v1/printers/{$centerPrinter->id}")->assertNotFound();
        $this->getJson("/api/v1/assets/{$branchPrinter->id}")->assertOk()->assertJsonPath('data.category.code', 'printer');

        $this->actingAs($this->head);
        $this->assertEqualsCanonicalizing([$branchPrinter->id, $centerPrinter->id], array_column($this->getJson('/api/v1/printers')->json('data'), 'id'));
        $this->getJson("/api/v1/printers?filter[organization]={$this->branch->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/printers?filter[connection]=network')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/printers?filter[cartridge]={$fits->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $branchPrinter->id);
        $this->getJson("/api/v1/printers?search={$centerPrinter->inventory_number}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $centerPrinter->id);
        $this->getJson("/api/v1/printers/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/printers?search=10.10.1.25')->assertJsonCount(1, 'data');
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->branch))->getJson('/api/v1/printers')->assertForbidden();
    }

    public function test_the_printer_page_offers_compatible_cartridges_with_stock_per_visible_warehouse_and_repairs(): void
    {
        $printer = $this->printerIn($this->branch, $this->head, ['model_id' => $this->m404->id, 'manufacturer_id' => $this->m404->manufacturer_id]);
        $fits = $this->cartridge(['name' => 'HP 59A', 'part_number' => 'CF259A'], [$this->m404]);
        $other = $this->cartridge(['name' => 'HP 12A', 'part_number' => 'Q2612A'], [$this->printerModel('LaserJet 1020')]);
        $this->cartridge(['name' => 'HP 59X', 'part_number' => 'CF259X', 'is_active' => false], [$this->m404]);
        $branchWarehouse = $this->warehouseIn($this->branch, ['name' => 'Xiva ombori']);
        $this->stockUp($branchWarehouse, $fits, 3, $this->head);
        $this->stockUp($this->warehouseIn($this->center), $fits, 2, $this->head);
        AssetMaintenance::query()->create(['asset_id' => $printer->id, 'status' => 'open', 'started_at' => '2026-11-02 10:00:00',
            'description' => 'Qog\'oz tiqilib qolyapti', 'created_by' => $this->head->id]);

        $data = $this->actingAs($this->it)->getJson("/api/v1/printers/{$printer->id}")->assertOk()
            ->assertJsonPath('data.compatibility_defined', true)->assertJsonCount(1, 'data.cartridges')
            ->assertJsonPath('data.cartridges.0.id', $fits->id)->assertJsonPath('data.cartridges.0.stock', '3.000')
            ->assertJsonPath('data.maintenances.0.description', 'Qog\'oz tiqilib qolyapti')
            ->assertJsonPath('data.can.service', true)->json('data');
        $this->assertSame([['warehouse' => ['id' => $branchWarehouse->id, 'name' => 'Xiva ombori', 'organization' => 'Xiva GTS'], 'quantity' => '3.000']],
            $data['cartridges'][0]['warehouses']);
        $this->assertCount(12, $data['monthly_pages']);

        $bare = $this->printerIn($this->branch, $this->head);
        $ids = array_column($this->getJson("/api/v1/printers/{$bare->id}")->assertOk()->assertJsonPath('data.compatibility_defined', false)->json('data.cartridges'), 'id');
        $this->assertEqualsCanonicalizing([$fits->id, $other->id], $ids);
    }

    public function test_service_users_edit_the_profile_and_viewers_only_read(): void
    {
        $printer = $this->printerIn($this->branch, $this->head);

        $this->actingAs($this->it)->putJson("/api/v1/printers/{$printer->id}", ['connection_type' => 'usb', 'is_color' => true, 'note' => 'Buxgalteriya xonasida'])
            ->assertOk()->assertJsonPath('data.connection_type', 'usb')->assertJsonPath('data.is_color', true);
        $this->assertDatabaseHas('printers', ['asset_id' => $printer->id, 'connection_type' => 'usb', 'is_color' => true]);
        $this->putJson("/api/v1/printers/{$printer->id}", ['connection_type' => 'fax'])->assertStatus(422)->assertJsonValidationErrors('connection_type');

        $orgHead = $this->makeUser(Roles::ORG_HEAD, $this->branch);
        $this->actingAs($orgHead)->getJson("/api/v1/printers/{$printer->id}")->assertOk()->assertJsonPath('data.can.service', false);
        $this->putJson("/api/v1/printers/{$printer->id}", ['connection_type' => 'network'])->assertForbidden();

        $printer->forceFill(['status' => AssetStatus::WrittenOff])->save();
        $this->actingAs($this->it)->putJson("/api/v1/printers/{$printer->id}", ['connection_type' => 'network'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }
}
