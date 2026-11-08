<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockDocumentType;
use App\Models\Material;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class StockCountTest extends TestCase
{
    use CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private Warehouse $warehouse;

    private Material $toner;

    private Material $battery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarehouse();
        $this->org = Organization::factory()->create();
        $this->warehouse = $this->warehouseIn($this->org);
        $this->toner = $this->material(['name' => 'Toner']);
        $this->battery = $this->material(['name' => 'Batareya', 'track_batches' => true]);
        app(StockService::class)->post(StockDocumentType::Receipt, ['warehouse_id' => $this->warehouse->id], [
            ['material_id' => $this->toner->id, 'quantity' => 5, 'unit_cost' => 100],
            ['material_id' => $this->battery->id, 'quantity' => 3, 'batch' => 'B1', 'unit_cost' => 40],
        ], User::factory()->create());
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));
    }

    private function start(): int
    {
        return $this->postJson('/api/v1/stock-counts', ['warehouse_id' => $this->warehouse->id, 'note' => 'Yillik'])
            ->assertCreated()->json('data.id');
    }

    /** @return array<string, int> line id by material name and batch */
    private function lineIds(int $count): array
    {
        return collect($this->getJson("/api/v1/stock-counts/{$count}")->assertOk()->json('data.lines'))
            ->mapWithKeys(fn ($l) => [$l['material']['name'].($l['batch'] ? ':'.$l['batch'] : '') => $l['id']])->all();
    }

    public function test_start_snapshots_lots_and_allows_one_open_count_per_warehouse(): void
    {
        $count = $this->start();

        $this->getJson("/api/v1/stock-counts/{$count}")->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.progress.total', 2)
            ->assertJsonPath('data.progress.counted', 0);
        $this->assertMatchesRegularExpression('/^SC-\d{4}-00001$/', $this->getJson("/api/v1/stock-counts/{$count}")->json('data.number'));
        $this->postJson('/api/v1/stock-counts', ['warehouse_id' => $this->warehouse->id])->assertStatus(409);

        $foreign = $this->warehouseIn(Organization::factory()->create());
        $this->postJson('/api/v1/stock-counts', ['warehouse_id' => $foreign->id])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');

        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org));
        $this->getJson("/api/v1/stock-counts/{$count}")->assertOk();
        $this->postJson("/api/v1/stock-counts/{$count}/cancel")->assertForbidden();
    }

    public function test_open_count_freezes_stock_documents_of_the_warehouse(): void
    {
        $this->start();

        $this->postJson('/api/v1/stock-documents', ['type' => 'receipt', 'warehouse_id' => $this->warehouse->id,
            'lines' => [['material_id' => $this->toner->id, 'quantity' => 1]]])
            ->assertStatus(409)->assertJsonPath('code', 'warehouse_counting');
    }

    public function test_close_posts_one_adjustment_with_the_differences(): void
    {
        $count = $this->start();
        $ids = $this->lineIds($count);

        $this->patchJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [['id' => $ids['Toner'], 'counted_quantity' => 4]]])->assertOk();
        $this->postJson("/api/v1/stock-counts/{$count}/lines", ['material_id' => $this->battery->id, 'batch' => 'B2', 'counted_quantity' => 2])
            ->assertCreated();
        $this->postJson("/api/v1/stock-counts/{$count}/lines", ['material_id' => $this->battery->id, 'batch' => 'B2', 'counted_quantity' => 1])
            ->assertStatus(422);
        $this->patchJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [['id' => $ids['Toner'], 'counted_quantity' => -1]]])
            ->assertStatus(422);

        $this->postJson("/api/v1/stock-counts/{$count}/close")->assertStatus(422)->assertJsonValidationErrors('lines');

        $this->patchJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [['id' => $ids['Batareya:B1'], 'counted_quantity' => 3]]])->assertOk();
        $closed = $this->postJson("/api/v1/stock-counts/{$count}/close")->assertOk()
            ->assertJsonPath('data.status', 'closed')->json('data');

        $adjustment = StockDocument::query()->findOrFail($closed['adjustment_document']['id']);
        $this->assertSame(StockDocumentType::Adjustment, $adjustment->type);
        $this->assertSame($count, $adjustment->stock_count_id);
        $this->assertEqualsCanonicalizing(['-1.000', '2.000'], $adjustment->lines()->pluck('quantity')->all());
        $this->assertSame('4.000', StockBalance::query()->where('material_id', $this->toner->id)->value('quantity'));
        $this->assertSame('2.000', StockBalance::query()->where('material_id', $this->battery->id)->where('batch', 'B2')->value('quantity'));

        $this->postJson('/api/v1/stock-documents', ['type' => 'receipt', 'warehouse_id' => $this->warehouse->id,
            'lines' => [['material_id' => $this->toner->id, 'quantity' => 1]]])->assertCreated();
        $this->postJson("/api/v1/stock-counts/{$count}/close")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_close_without_differences_posts_nothing(): void
    {
        $count = $this->start();
        $ids = $this->lineIds($count);
        $this->patchJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [
            ['id' => $ids['Toner'], 'counted_quantity' => 5], ['id' => $ids['Batareya:B1'], 'counted_quantity' => '3.000'],
        ]])->assertOk();

        $this->postJson("/api/v1/stock-counts/{$count}/close")->assertOk()->assertJsonPath('data.adjustment_document', null);
        $this->assertSame(0, StockDocument::query()->where('type', StockDocumentType::Adjustment)->count());
    }

    public function test_cancel_lifts_the_freeze(): void
    {
        $count = $this->start();
        $this->postJson("/api/v1/stock-counts/{$count}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->patchJson("/api/v1/stock-counts/{$count}/lines", ['lines' => []])->assertStatus(422);

        $this->getJson('/api/v1/stock-counts?filter[status]=cancelled')->assertOk()->assertJsonCount(1, 'data');
        $this->start();
    }
}
