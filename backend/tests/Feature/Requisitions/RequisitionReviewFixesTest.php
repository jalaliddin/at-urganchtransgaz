<?php

namespace Tests\Feature\Requisitions;

use App\Enums\RequisitionDecision;
use App\Enums\StockDocumentType;
use App\Models\AssetTransfer;
use App\Models\Organization;
use App\Models\Requisition;
use App\Models\RequisitionFulfillment;
use App\Models\User;
use App\Services\Requisitions\RequisitionWorkflow;
use App\Services\Settings\SettingsService;
use App\Services\Warehouse\StockService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesRequisitions;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class RequisitionReviewFixesTest extends TestCase
{
    use CreatesAssets, CreatesRequisitions, CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $it;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedWarehouse();
        $this->seedAssets();
        app(SettingsService::class)->update(['requisitions.head_approval' => false]);
        $this->center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
    }

    private function approved(array $items): Requisition
    {
        $requisition = $this->draftFor($this->branch, $this->it, $items);
        $workflow = app(RequisitionWorkflow::class);
        $workflow->submit($requisition, $this->it);
        $workflow->decide($requisition, RequisitionDecision::Approved, null, [], $this->head);

        return $requisition;
    }

    public function test_a_transfer_carrying_a_requisition_allocation_cannot_be_cancelled(): void
    {
        $r = $this->approved([['kind' => 'equipment', 'name' => 'Monitor', 'quantity' => 1]]);
        $monitor = $this->assetIn($this->center, $this->head, ['name' => 'Dell P2422H']);
        $this->actingAs($this->head)->postJson("/api/v1/requisitions/{$r->id}/allocations",
            ['item_id' => $r->items()->value('id'), 'type' => 'asset', 'asset_id' => $monitor->id])->assertCreated();
        $transfer = AssetTransfer::query()->firstOrFail();

        $this->postJson("/api/v1/asset-transfers/{$transfer->id}/cancel")->assertStatus(409);

        $this->actingAs($this->it)->postJson("/api/v1/requisitions/{$r->id}/receive")->assertOk()->assertJsonPath('data.status', 'received');
    }

    public function test_the_allocation_note_is_kept_for_stock_and_assets(): void
    {
        $warehouse = $this->warehouseIn($this->center);
        $toner = $this->material();
        app(StockService::class)->post(StockDocumentType::Receipt, ['warehouse_id' => $warehouse->id], [['material_id' => $toner->id, 'quantity' => 5]], $this->head);
        $r = $this->approved([['kind' => 'consumable', 'material_id' => $toner->id, 'name' => 'Toner', 'quantity' => 2]]);

        $this->actingAs($this->head)->postJson("/api/v1/requisitions/{$r->id}/allocations", [
            'item_id' => $r->items()->value('id'), 'type' => 'stock', 'warehouse_id' => $warehouse->id, 'quantity' => 2, 'note' => '12-xona uchun',
        ])->assertCreated()->assertJsonPath('data.fulfillments.0.note', '12-xona uchun');
        $this->assertSame('12-xona uchun', RequisitionFulfillment::query()->value('note'));
    }

    public function test_a_draft_that_was_already_submitted_keeps_its_history(): void
    {
        $r = $this->draftFor($this->branch, $this->it);
        app(RequisitionWorkflow::class)->submit($r, $this->it);
        app(RequisitionWorkflow::class)->decide($r, RequisitionDecision::Returned, 'Asoslang', [], $this->head);

        $this->actingAs($this->it)->getJson("/api/v1/requisitions/{$r->id}")->assertOk()
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.can.delete', false)->assertJsonPath('data.can.edit', true);
        $this->deleteJson("/api/v1/requisitions/{$r->id}")->assertStatus(422);
        $this->assertNotNull(Requisition::query()->withoutGlobalScopes()->find($r->id));
    }
}
