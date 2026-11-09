<?php

namespace Tests\Feature\Printers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\CartridgeModel;
use App\Models\CartridgeReplacement;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\PrinterCounter;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockCountService;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPrinters;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class CartridgeReplacementTest extends TestCase
{
    use CreatesPrinters, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $it;

    private AssetModel $m404;

    private Asset $printer;

    private Employee $holder;

    private CartridgeModel $toner;

    private Warehouse $warehouse;

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
        $this->holder = Employee::factory()->create(['organization_id' => $this->branch->id]);
        $this->printer = $this->printerIn($this->branch, $this->head, ['model_id' => $this->m404->id, 'manufacturer_id' => $this->m404->manufacturer_id]);
        $this->printer->forceFill(['holder_employee_id' => $this->holder->id])->save();
        $this->toner = $this->cartridge(['name' => 'HP 59A', 'part_number' => 'CF259A'], [$this->m404]);
        $this->warehouse = $this->warehouseIn($this->branch, ['name' => 'Xiva ombori']);
        $this->stockUp($this->warehouse, $this->toner, 3, $this->head);
    }

    /** @param  array<string, mixed>  $payload */
    private function replace(array $payload = [], ?User $as = null, ?Asset $printer = null): TestResponse
    {
        return $this->actingAs($as ?? $this->it)->postJson('/api/v1/printers/'.($printer ?? $this->printer)->id.'/replacements', [
            'cartridge_model_id' => $this->toner->id, 'replaced_at' => '2026-11-10', 'source' => 'warehouse', 'warehouse_id' => $this->warehouse->id,
            ...$payload,
        ]);
    }

    private function onHand(?Warehouse $warehouse = null): string
    {
        return (string) StockBalance::query()->withoutGlobalScopes()->where('warehouse_id', ($warehouse ?? $this->warehouse)->id)
            ->where('material_id', $this->toner->material_id)->sum('quantity');
    }

    public function test_a_replacement_from_the_warehouse_issues_one_cartridge_to_the_printer_and_records_the_counter(): void
    {
        $response = $this->replace(['note' => 'Eski kartrij zapravkaga', 'counter' => ['total' => 15000]])->assertCreated()
            ->assertJsonPath('data.source', 'warehouse')->assertJsonPath('data.cost', '250000.00')
            ->assertJsonPath('data.warehouse.id', $this->warehouse->id)->assertJsonPath('data.counter.total', 15000)
            ->assertJsonPath('data.cartridge.id', $this->toner->id)->assertJsonPath('data.organization.id', $this->branch->id)
            ->assertJsonPath('data.performed_by.id', $this->it->id)->assertJsonPath('data.can_cancel', true);

        $document = StockDocument::query()->withoutGlobalScopes()->findOrFail($response->json('data.stock_document.id'));
        $this->assertSame('issue', $document->type->value);
        $this->assertStringStartsWith('IS-2026-', $document->number);
        $this->assertSame([$this->printer->id, $this->branch->id, $this->holder->id, '2026-11-10'],
            [$document->asset_id, $document->recipient_organization_id, $document->recipient_employee_id, $document->document_date->toDateString()]);
        $this->assertSame('2.000', $this->onHand());

        $counter = PrinterCounter::query()->withoutGlobalScopes()->where('asset_id', $this->printer->id)->sole();
        $this->assertSame(['replacement', $response->json('data.id'), '2026-11-10'], [$counter->source->value, $counter->replacement_id, $counter->read_at->toDateString()]);
        $this->getJson("/api/v1/printers/{$this->printer->id}")->assertJsonPath('data.last_replacement.cartridge', 'HP 59A')
            ->assertJsonPath('data.cartridges.0.stock', '2.000');
    }

    public function test_source_compatibility_date_and_permission_rules(): void
    {
        $this->replace(['source' => 'other', 'warehouse_id' => null])->assertCreated()->assertJsonPath('data.cost', null)
            ->assertJsonPath('data.stock_document', null);
        $this->assertSame('3.000', $this->onHand());

        $elsewhere = $this->cartridge(['name' => 'HP 12A', 'part_number' => 'Q2612A'], [$this->printerModel('LaserJet 1020')]);
        $this->replace(['cartridge_model_id' => $elsewhere->id])->assertStatus(422)->assertJsonValidationErrors('cartridge_model_id');
        $noModel = $this->printerIn($this->branch, $this->head);
        $this->replace(['cartridge_model_id' => $elsewhere->id, 'source' => 'other'], null, $noModel)->assertCreated();
        $retired = $this->cartridge(['name' => 'HP 59X', 'part_number' => 'CF259X', 'is_active' => false], [$this->m404]);
        $this->replace(['cartridge_model_id' => $retired->id])->assertStatus(422)->assertJsonValidationErrors('cartridge_model_id');
        $this->replace(['replaced_at' => '2026-11-21'])->assertStatus(422)->assertJsonValidationErrors('replaced_at');
        $this->replace(['warehouse_id' => null])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
        $foreign = $this->warehouseIn(Organization::factory()->create());
        $this->replace(['warehouse_id' => $foreign->id])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');

        Role::findOrCreate('printer-tech', 'web')->givePermissionTo(['printers.view', 'printers.service']);
        $tech = $this->makeUser('printer-tech', $this->branch);
        $this->replace([], $tech)->assertForbidden();
        $this->replace(['source' => 'other', 'warehouse_id' => null], $tech)->assertCreated();
        $this->replace([], $this->makeUser(Roles::ORG_HEAD, $this->branch))->assertForbidden();
        $this->replace([], $this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()))->assertNotFound();
    }

    public function test_a_failing_stock_issue_or_counter_leaves_nothing_behind(): void
    {
        $empty = $this->warehouseIn($this->branch);
        $this->replace(['warehouse_id' => $empty->id, 'counter' => ['total' => 15000]])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');

        $this->actingAs($this->it)->postJson("/api/v1/printers/{$this->printer->id}/counters", ['read_at' => '2026-11-01', 'total' => 20000])->assertCreated();
        $this->replace(['counter' => ['total' => 15000]])->assertStatus(422)->assertJsonValidationErrors('counter.total');
        $this->assertSame('3.000', $this->onHand());

        app(StockCountService::class)->start($this->warehouse, null, $this->head);
        $this->replace()->assertStatus(409);

        $this->assertSame(0, CartridgeReplacement::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, PrinterCounter::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, StockDocument::query()->withoutGlobalScopes()->where('type', 'issue')->count());
    }

    public function test_cancelling_returns_the_cartridge_to_its_warehouse_once(): void
    {
        $id = $this->replace()->assertCreated()->json('data.id');
        $this->assertSame('2.000', $this->onHand());
        $cancel = fn (array $payload = [], ?User $as = null) => $this->actingAs($as ?? $this->it)->postJson("/api/v1/cartridge-replacements/{$id}/cancel", $payload);

        $cancel()->assertStatus(422)->assertJsonValidationErrors('reason');
        Role::findOrCreate('printer-tech', 'web')->givePermissionTo(['printers.view', 'printers.service']);
        $cancel(['reason' => 'Xato kiritildi'], $this->makeUser('printer-tech', $this->branch))->assertForbidden();
        $cancel(['reason' => 'Xato kiritildi'], $this->makeUser(Roles::ORG_HEAD, $this->branch))->assertForbidden();
        $cancel(['reason' => 'Xato kiritildi'], $this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()))->assertNotFound();

        $returnNumber = $cancel(['reason' => 'Xato kiritildi'])->assertOk()->assertJsonPath('data.cancel_reason', 'Xato kiritildi')
            ->assertJsonPath('data.cancelled_by.id', $this->it->id)->assertJsonPath('data.can_cancel', false)->json('data.return_document.number');
        $this->assertStringStartsWith('RT-2026-', $returnNumber);
        $this->assertSame('3.000', $this->onHand());
        $cancel(['reason' => 'Yana'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->assertSame('3.000', $this->onHand());
        $this->getJson("/api/v1/printers/{$this->printer->id}")->assertJsonPath('data.last_replacement', null);

        $other = $this->replace(['source' => 'other', 'warehouse_id' => null])->json('data.id');
        $this->actingAs($this->it)->postJson("/api/v1/cartridge-replacements/{$other}/cancel", ['reason' => 'Boshqa printer edi'])
            ->assertOk()->assertJsonPath('data.return_document', null);
    }

    public function test_history_shows_pages_since_the_previous_cartridge_and_the_journal_filters_in_scope(): void
    {
        $this->replace(['source' => 'other', 'warehouse_id' => null, 'replaced_at' => '2026-10-01', 'counter' => ['total' => 10000]])->assertCreated();
        $this->replace(['replaced_at' => '2026-11-01', 'counter' => ['total' => 13000]])->assertCreated();
        $color = $this->cartridge(['name' => 'HP 207A cyan', 'part_number' => 'W2211A', 'color' => 'cyan'], [$this->m404]);
        $this->replace(['cartridge_model_id' => $color->id, 'source' => 'other', 'warehouse_id' => null, 'replaced_at' => '2026-11-05', 'counter' => ['total' => 14000]])->assertCreated();

        $history = $this->getJson("/api/v1/printers/{$this->printer->id}/replacements")->assertOk()->json('data');
        $this->assertSame([['2026-11-05', null], ['2026-11-01', 3000], ['2026-10-01', null]],
            array_map(fn ($r) => [$r['replaced_at'], $r['pages_since_previous']], $history));
        $this->assertSame(3000, $history[1]['cartridge']['page_yield']);

        $this->actingAs($this->head);
        $this->getJson('/api/v1/cartridge-replacements')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/v1/cartridge-replacements?filter[cartridge]={$color->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/cartridge-replacements?filter[source]=warehouse')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/cartridge-replacements?filter[date_from]=2026-10-15&filter[date_to]=2026-11-02')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/cartridge-replacements?filter[organization]={$this->branch->id}&filter[printer]={$this->printer->id}")->assertJsonCount(3, 'data');
        $this->getJson("/api/v1/cartridge-replacements?search={$this->printer->inventory_number}")->assertJsonCount(3, 'data');
        $last = $this->getJson('/api/v1/cartridge-replacements')->json('data.0.id');
        $this->postJson("/api/v1/cartridge-replacements/{$last}/cancel", ['reason' => 'Xato'])->assertOk();
        $this->getJson('/api/v1/cartridge-replacements?filter[cancelled]=0')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/cartridge-replacements?filter[cancelled]=1')->assertJsonCount(1, 'data');
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()))
            ->getJson('/api/v1/cartridge-replacements')->assertOk()->assertJsonCount(0, 'data');
    }
}
