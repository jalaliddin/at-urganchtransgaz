<?php

namespace Tests\Feature\Printers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CartridgeModel;
use App\Models\CartridgeReplacement;
use App\Models\Material;
use App\Models\Organization;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Exports\CartridgeReplacementReport;
use App\Services\Exports\PrinterConsumptionReport;
use App\Services\Exports\PrinterReport;
use App\Services\Printers\CartridgeReplacements;
use App\Services\Printers\PrinterCounters;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesPrinters;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class PrinterReportsTest extends TestCase
{
    use CreatesPrinters, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $it;

    private AssetModel $m404;

    private CartridgeModel $toner;

    private CartridgeModel $cyan;

    private Warehouse $warehouse;

    private Asset $branchPrinter;

    private Asset $centerPrinter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPrinters();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Tashkent'));
        $this->center = Organization::factory()->create(['name' => 'Urganchtransgaz']);
        $this->branch = Organization::factory()->create(['name' => 'Xiva GTS', 'parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->m404 = $this->printerModel();
        $this->toner = $this->cartridge(['name' => 'HP 59A', 'part_number' => 'CF259A'], [$this->m404]);
        $this->cyan = $this->cartridge(['name' => 'HP 207A cyan', 'part_number' => 'W2211A', 'color' => 'cyan']);
        $this->warehouse = $this->warehouseIn($this->branch);
        $this->stockUp($this->warehouse, $this->toner, 3, $this->head);
        $this->branchPrinter = $this->printerIn($this->branch, $this->head, ['model_id' => $this->m404->id, 'manufacturer_id' => $this->m404->manufacturer_id]);
        $this->centerPrinter = $this->printerIn($this->center, $this->head);
    }

    /** @param  array<string, mixed>  $data */
    private function replaced(Asset $printer, CartridgeModel $cartridge, string $date, array $data = []): int
    {
        return app(CartridgeReplacements::class)->replace($printer, [
            'cartridge_model_id' => $cartridge->id, 'replaced_at' => $date, 'source' => 'other', ...$data,
        ], $this->head)->id;
    }

    private function read(Asset $printer, string $date, int $total): void
    {
        app(PrinterCounters::class)->record($printer, ['read_at' => $date, 'total' => $total], $this->head);
    }

    private function history(): void
    {
        $this->read($this->branchPrinter, '2026-01-05', 5000);
        $this->replaced($this->branchPrinter, $this->toner, '2026-03-10', ['source' => 'warehouse', 'warehouse_id' => $this->warehouse->id, 'counter' => ['total' => 10000]]);
        $this->replaced($this->branchPrinter, $this->toner, '2026-06-01', ['counter' => ['total' => 13000]]);
        $this->replaced($this->branchPrinter, $this->toner, '2025-12-20');
        $cancelled = $this->replaced($this->branchPrinter, $this->toner, '2026-07-01', ['source' => 'warehouse', 'warehouse_id' => $this->warehouse->id]);
        app(CartridgeReplacements::class)->cancel(CartridgeReplacement::query()->withoutGlobalScopes()->findOrFail($cancelled), 'Xato', $this->head);
        $this->read($this->centerPrinter, '2026-02-01', 1000);
        $this->read($this->centerPrinter, '2026-04-01', 1600);
        $this->replaced($this->centerPrinter, $this->cyan, '2026-05-05');
        $foreign = $this->printerIn(Organization::factory()->create(), $this->head);
        $this->replaced($foreign, $this->toner, '2026-05-05');
    }

    public function test_consumption_is_grouped_by_organization_cartridge_and_printer_within_scope(): void
    {
        $this->history();
        $this->actingAs($this->head);

        $report = $this->getJson('/api/v1/printer-report?group_by=organization&date_from=2026-01-01&date_to=2026-12-31')->assertOk()->json('data');
        $rows = collect($report['rows'])->keyBy('key.id');
        $this->assertCount(2, $rows);
        $this->assertSame(['replacements' => 2, 'cost' => '250000.00', 'pages' => 8000, 'cost_per_page' => '31.25'],
            array_intersect_key($rows[$this->branch->id], array_flip(['replacements', 'cost', 'pages', 'cost_per_page'])));
        $this->assertSame(['replacements' => 1, 'cost' => '0.00', 'pages' => 600, 'cost_per_page' => null],
            array_intersect_key($rows[$this->center->id], array_flip(['replacements', 'cost', 'pages', 'cost_per_page'])));
        $this->assertSame(['replacements' => 3, 'cost' => '250000.00', 'pages' => 8600], array_intersect_key($report['total'], array_flip(['replacements', 'cost', 'pages'])));
        $months = collect($report['monthly'])->keyBy('month');
        $this->assertCount(12, $months);
        $this->assertSame(['month' => '2026-03', 'replacements' => 1, 'cost' => '250000.00', 'pages' => 5000], $months['2026-03']);
        $this->assertSame(['month' => '2026-04', 'replacements' => 0, 'cost' => '0.00', 'pages' => 600], $months['2026-04']);

        $this->assertEquals($report, $this->getJson('/api/v1/printer-report?group_by=organization')->json('data'));
        $byCartridge = collect($this->getJson('/api/v1/printer-report?group_by=cartridge')->json('data.rows'))->keyBy('key.id');
        $this->assertSame([2, null, 'CF259A'], [$byCartridge[$this->toner->id]['replacements'], $byCartridge[$this->toner->id]['pages'], $byCartridge[$this->toner->id]['key']['code']]);
        $byPrinter = collect($this->getJson('/api/v1/printer-report?group_by=printer')->json('data.rows'))->keyBy('key.id');
        $this->assertSame([$this->branchPrinter->inventory_number, 8000], [$byPrinter[$this->branchPrinter->id]['key']['code'], $byPrinter[$this->branchPrinter->id]['pages']]);
        $this->getJson("/api/v1/printer-report?group_by=printer&organization={$this->branch->id}")->assertJsonCount(1, 'data.rows');

        $this->getJson('/api/v1/printer-report?group_by=room')->assertStatus(422)->assertJsonValidationErrors('group_by');
        $this->getJson('/api/v1/printer-report?date_from=2026-05-01&date_to=2026-04-01')->assertStatus(422)->assertJsonValidationErrors('date_to');
        $this->getJson('/api/v1/printer-report?date_from=2020-01-01&date_to=2026-04-01')->assertStatus(422)->assertJsonValidationErrors('date_to');

        $this->actingAs($this->it)->getJson('/api/v1/printer-report?group_by=organization')->assertOk()
            ->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.key.id', $this->branch->id);
    }

    public function test_the_dashboard_tile_counts_printers_replacements_this_month_and_cartridge_stock(): void
    {
        Material::query()->whereKey($this->toner->material_id)->update(['min_quantity' => 5]);
        $this->replaced($this->branchPrinter, $this->toner, '2026-11-05', ['source' => 'warehouse', 'warehouse_id' => $this->warehouse->id]);
        $this->replaced($this->branchPrinter, $this->toner, '2026-10-28');

        $this->actingAs($this->it)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.printers.printers', 1)->assertJsonPath('data.printers.replacements_month', 1)
            ->assertJsonPath('data.printers.cartridges_in_stock', '2.000')->assertJsonPath('data.printers.low_stock', 1);
        $this->actingAs($this->head)->getJson('/api/v1/dashboard')->assertJsonPath('data.printers.printers', 2);
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->branch))->getJson('/api/v1/dashboard')->assertOk()->assertJsonMissingPath('data.printers');
    }

    public function test_exports_apply_scope(): void
    {
        $this->history();
        Auth::forgetGuards();

        $printers = iterator_to_array(app(PrinterReport::class)->rows($this->it, [], 'uz'), false);
        $this->assertSame([$this->branchPrinter->inventory_number], array_column($printers, 0));
        $journal = iterator_to_array(app(CartridgeReplacementReport::class)->rows($this->it, [], 'uz'), false);
        $this->assertCount(4, $journal);
        $consumption = iterator_to_array(app(PrinterConsumptionReport::class)->rows($this->it, ['group_by' => 'organization'], 'uz'), false);
        $this->assertSame([['Xiva GTS', 2, '250000.00', 8000, '31.25']], $consumption);
    }
}
