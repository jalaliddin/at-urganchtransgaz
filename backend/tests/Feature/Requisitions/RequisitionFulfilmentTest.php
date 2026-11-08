<?php

namespace Tests\Feature\Requisitions;

use App\Enums\RequisitionDecision;
use App\Enums\StockDocumentType;
use App\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Material;
use App\Models\Organization;
use App\Models\Requisition;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Requisitions\RequisitionNotification;
use App\Services\Exports\RequisitionReport;
use App\Services\Requisitions\RequisitionWorkflow;
use App\Services\Settings\SettingsService;
use App\Services\Warehouse\StockService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesRequisitions;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class RequisitionFulfilmentTest extends TestCase
{
    use CreatesAssets, CreatesRequisitions, CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $it;

    private User $staff;

    private User $head;

    private Warehouse $central;

    private Material $toner;

    private Requisition $requisition;

    /** @var array<string, int> item ids by kind */
    private array $items;

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
        $this->staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->center);
        $this->grantAccess($this->staff, $this->branch);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center);
        $this->grantAccess($this->head, $this->branch);

        $this->central = $this->warehouseIn($this->center, ['name' => 'Markaziy ombor']);
        $this->toner = $this->material(['name' => 'HP 85A']);
        app(StockService::class)->post(StockDocumentType::Receipt, ['warehouse_id' => $this->central->id],
            [['material_id' => $this->toner->id, 'quantity' => 10, 'unit_cost' => 150000]], $this->head);

        $this->requisition = $this->draftFor($this->branch, $this->it, [
            ['kind' => 'consumable', 'material_id' => $this->toner->id, 'name' => 'HP 85A', 'quantity' => 4],
            ['kind' => 'equipment', 'name' => 'Monitor', 'quantity' => 1, 'asset_category_id' => $this->assetCategory('monitor')->id],
            ['kind' => 'internet', 'name' => 'Internet 50 Mbit', 'quantity' => 1],
        ]);
        $workflow = app(RequisitionWorkflow::class);
        $workflow->submit($this->requisition, $this->it);
        $workflow->decide($this->requisition, RequisitionDecision::Approved, null, [], $this->head);
        $this->items = $this->requisition->items()->pluck('id', 'kind')->map(fn ($id) => (int) $id)->all();
    }

    private function allocate(User $user, array $payload): TestResponse
    {
        return $this->actingAs($user)->postJson("/api/v1/requisitions/{$this->requisition->id}/allocations", $payload);
    }

    public function test_stock_allocation_issues_to_the_requesting_organization_within_the_remainder(): void
    {
        Notification::fake();
        $this->allocate($this->it, ['item_id' => $this->items['consumable'], 'type' => 'stock', 'warehouse_id' => $this->central->id, 'quantity' => 1])
            ->assertForbidden();

        $this->allocate($this->staff, ['item_id' => $this->items['consumable'], 'type' => 'stock', 'warehouse_id' => $this->central->id, 'quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('data.status', 'partially_fulfilled')
            ->assertJsonPath('data.items.0.fulfilled_quantity', '3.000')
            ->assertJsonPath('data.items.0.remaining', '1.000')
            ->assertJsonPath('data.fulfillments.0.stock_document.type', 'issue');
        Notification::assertSentTo($this->it, RequisitionNotification::class, fn ($n) => $n->event === 'partially_fulfilled');

        $issue = StockDocument::query()->where('requisition_id', $this->requisition->id)->firstOrFail();
        $this->assertSame($this->branch->id, $issue->recipient_organization_id);
        $this->assertSame('7.000', StockBalance::query()->where('warehouse_id', $this->central->id)->value('quantity'));

        $this->allocate($this->staff, ['item_id' => $this->items['consumable'], 'type' => 'stock', 'warehouse_id' => $this->central->id, 'quantity' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');
        $this->allocate($this->staff, ['item_id' => $this->items['internet'], 'type' => 'stock', 'warehouse_id' => $this->central->id, 'quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('material_id');
    }

    public function test_stock_allocation_can_transfer_into_a_warehouse_of_the_requesting_organization(): void
    {
        $branchStore = $this->warehouseIn($this->branch, ['name' => 'Filial ombori']);
        $otherStore = $this->warehouseIn($this->center, ['name' => 'Boshqa']);

        $this->allocate($this->staff, ['item_id' => $this->items['consumable'], 'type' => 'stock', 'warehouse_id' => $this->central->id,
            'target_warehouse_id' => $otherStore->id, 'quantity' => 1])->assertStatus(422)->assertJsonValidationErrors('target_warehouse_id');
        $this->allocate($this->staff, ['item_id' => $this->items['consumable'], 'type' => 'stock', 'warehouse_id' => $this->central->id,
            'target_warehouse_id' => $branchStore->id, 'quantity' => 4])->assertCreated()
            ->assertJsonPath('data.fulfillments.0.stock_document.type', 'transfer');

        $this->assertSame('4.000', StockBalance::query()->where('warehouse_id', $branchStore->id)->value('quantity'));
    }

    public function test_asset_from_another_organization_travels_by_transfer_and_arrives_on_receipt(): void
    {
        Notification::fake();
        $monitor = $this->assetIn($this->center, $this->head, ['name' => 'Dell P2422H', 'category_id' => $this->assetCategory('monitor')->id]);

        $this->allocate($this->staff, ['item_id' => $this->items['equipment'], 'type' => 'asset', 'asset_id' => $monitor->id])
            ->assertCreated()->assertJsonPath('data.fulfillments.0.asset_transfer.status', 'sent');
        $this->allocate($this->staff, ['item_id' => $this->items['internet'], 'type' => 'note', 'quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('note');
        $this->allocate($this->staff, ['item_id' => $this->items['internet'], 'type' => 'note', 'quantity' => 1, 'note' => 'Uzonline shartnomasi №45'])
            ->assertCreated()->assertJsonPath('data.status', 'partially_fulfilled');
        $this->allocate($this->staff, ['item_id' => $this->items['consumable'], 'type' => 'stock', 'warehouse_id' => $this->central->id, 'quantity' => 4])
            ->assertCreated()->assertJsonPath('data.status', 'fulfilled');
        Notification::assertSentTo($this->it, RequisitionNotification::class, fn ($n) => $n->event === 'fulfilled');

        $this->actingAs($this->staff)->postJson("/api/v1/requisitions/{$this->requisition->id}/receive")->assertForbidden();
        $this->actingAs($this->it)->postJson("/api/v1/requisitions/{$this->requisition->id}/receive", ['comment' => 'Hammasi keldi'])
            ->assertOk()->assertJsonPath('data.status', 'received');

        $monitor = Asset::query()->withoutGlobalScopes()->findOrFail($monitor->id);
        $this->assertSame($this->branch->id, $monitor->organization_id);
        $this->assertSame(TransferStatus::Received, AssetTransfer::query()->firstOrFail()->status);

        $this->actingAs($this->it)->postJson("/api/v1/requisitions/{$this->requisition->id}/close")->assertForbidden();
        $this->actingAs($this->head)->postJson("/api/v1/requisitions/{$this->requisition->id}/close")->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_asset_allocation_checks_availability_and_same_organization_links_directly(): void
    {
        $local = $this->assetIn($this->branch, $this->head, ['name' => 'Monitor zaxira']);
        $broken = $this->assetIn($this->center, $this->head, ['name' => 'Buzuq', 'status' => 'in_use']);

        $this->allocate($this->staff, ['item_id' => $this->items['equipment'], 'type' => 'asset', 'asset_id' => $broken->id])
            ->assertStatus(422)->assertJsonValidationErrors('asset_id');
        $this->allocate($this->staff, ['item_id' => $this->items['equipment'], 'type' => 'asset', 'asset_id' => $local->id])
            ->assertCreated()->assertJsonPath('data.fulfillments.0.asset.id', $local->id)->assertJsonPath('data.fulfillments.0.asset_transfer', null);
        $this->assertSame(0, AssetTransfer::query()->count());
    }

    public function test_partial_allocation_can_be_finished_with_a_comment(): void
    {
        $this->allocate($this->staff, ['item_id' => $this->items['consumable'], 'type' => 'stock', 'warehouse_id' => $this->central->id, 'quantity' => 2]);

        $this->actingAs($this->staff)->postJson("/api/v1/requisitions/{$this->requisition->id}/complete")->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->actingAs($this->staff)->postJson("/api/v1/requisitions/{$this->requisition->id}/complete", ['comment' => 'Qolgani yo\'q'])
            ->assertOk()->assertJsonPath('data.status', 'fulfilled');
        $this->assertContains('completed', $this->requisition->histories()->pluck('action')->map->value->all());
    }

    public function test_export_dashboard_and_ready_step(): void
    {
        $this->actingAs($this->staff)->postJson("/api/v1/requisitions/{$this->requisition->id}/ready")->assertOk()->assertJsonPath('data.status', 'ready_for_allocation');
        $this->draftFor(Organization::factory()->create(), $this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()));

        $this->actingAs($this->staff)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.requisitions.awaiting_action', 1)
            ->assertJsonPath('data.requisitions.open', 1);

        Auth::forgetGuards();
        $this->assertFalse(Auth::hasUser());
        $rows = iterator_to_array(app(RequisitionReport::class)->rows($this->staff, [], 'uz'), false);
        $this->assertCount(1, $rows);
        $this->assertSame($this->requisition->number, $rows[0][0]);
    }
}
