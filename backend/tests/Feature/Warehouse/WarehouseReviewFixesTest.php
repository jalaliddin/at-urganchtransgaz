<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockDocumentType;
use App\Exceptions\InsufficientStockException;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\Warehouse\StockService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class WarehouseReviewFixesTest extends TestCase
{
    use CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarehouse();
    }

    public function test_the_recipient_organization_sees_an_issue_read_only(): void
    {
        $center = Organization::factory()->create();
        $branch = Organization::factory()->create();
        $warehouse = $this->warehouseIn($center);
        $material = $this->material();
        $stock = app(StockService::class);
        $head = User::factory()->create();
        $stock->post(StockDocumentType::Receipt, ['warehouse_id' => $warehouse->id], [['material_id' => $material->id, 'quantity' => 5]], $head);
        $issue = $stock->post(StockDocumentType::Issue, ['warehouse_id' => $warehouse->id, 'recipient_organization_id' => $branch->id, 'reason' => 'x'],
            [['material_id' => $material->id, 'quantity' => 2]], $head);

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $branch));
        $this->getJson("/api/v1/stock-documents/{$issue->id}")->assertOk()
            ->assertJsonPath('data.can.attach_file', false)
            ->assertJsonPath('data.can.return', false);
        $this->getJson('/api/v1/stock-documents')->assertOk()->assertJsonPath('meta.total', 1);
        $this->post("/api/v1/stock-documents/{$issue->id}/file", [], ['Accept' => 'application/json'])->assertForbidden();

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $center));
        $this->getJson("/api/v1/stock-documents/{$issue->id}")->assertOk()->assertJsonPath('data.can.return', true);
    }

    public function test_a_return_goes_back_into_the_warehouse_of_its_issue(): void
    {
        [$warehouse, $material, $org] = $this->stockFixture();
        $other = $this->warehouseIn($org);
        $stock = app(StockService::class);
        $user = User::factory()->create();
        $stock->post(StockDocumentType::Receipt, ['warehouse_id' => $warehouse->id], [['material_id' => $material->id, 'quantity' => 5]], $user);
        $issue = $stock->post(StockDocumentType::Issue, ['warehouse_id' => $warehouse->id, 'reason' => 'x'], [['material_id' => $material->id, 'quantity' => 2]], $user);

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $org));
        $this->postJson('/api/v1/stock-documents', ['type' => 'return', 'warehouse_id' => $other->id, 'related_document_id' => $issue->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 1]]])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
        $this->postJson('/api/v1/stock-documents', ['type' => 'return', 'warehouse_id' => $warehouse->id, 'related_document_id' => $issue->id,
            'lines' => [['material_id' => $material->id, 'quantity' => 1]]])->assertCreated();
    }

    public function test_fifo_leaves_expired_lots_unless_their_batch_is_named(): void
    {
        [$warehouse, , $org] = $this->stockFixture();
        $battery = $this->material(['name' => 'Batareya', 'track_batches' => true, 'track_expiry' => true]);
        $stock = app(StockService::class);
        $user = User::factory()->create();
        $stock->post(StockDocumentType::Receipt, ['warehouse_id' => $warehouse->id], [
            ['material_id' => $battery->id, 'quantity' => 5, 'batch' => 'OLD', 'expires_at' => now()->subDay()->toDateString()],
            ['material_id' => $battery->id, 'quantity' => 5, 'batch' => 'NEW', 'expires_at' => now()->addYear()->toDateString()],
        ], $user);

        $stock->post(StockDocumentType::Issue, ['warehouse_id' => $warehouse->id, 'reason' => 'x'], [['material_id' => $battery->id, 'quantity' => 3]], $user);
        $this->assertSame('5.000', StockBalance::query()->where('batch', 'OLD')->value('quantity'));
        $this->assertSame('2.000', StockBalance::query()->where('batch', 'NEW')->value('quantity'));

        try {
            $stock->post(StockDocumentType::Issue, ['warehouse_id' => $warehouse->id, 'reason' => 'x'], [['material_id' => $battery->id, 'quantity' => 4]], $user);
            $this->fail('expected a shortage');
        } catch (InsufficientStockException $e) {
            $this->assertSame('2.000', $e->shortages[0]['available']);
        }

        $stock->post(StockDocumentType::Adjustment, ['warehouse_id' => $warehouse->id, 'reason' => 'Muddati o\'tgan'],
            [['material_id' => $battery->id, 'quantity' => -5, 'batch' => 'OLD']], $user);
        $this->assertSame('0.000', StockBalance::query()->where('batch', 'OLD')->value('quantity'));
    }

    public function test_a_warehouse_that_only_had_a_stock_count_cannot_be_deleted(): void
    {
        $org = Organization::factory()->create();
        $warehouse = $this->warehouseIn($org);
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $org));
        $count = $this->postJson('/api/v1/stock-counts', ['warehouse_id' => $warehouse->id])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/stock-counts/{$count}/cancel")->assertOk();

        $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")->assertStatus(409);
    }
}
