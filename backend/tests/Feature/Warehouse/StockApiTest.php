<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockDocumentType;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Organization;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\Feature\Files\SafeUploadTest;
use Tests\TestCase;

class StockApiTest extends TestCase
{
    use CreatesAssets, CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private Organization $other;

    private Warehouse $warehouse;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarehouse();
        $this->org = Organization::factory()->create();
        $this->other = Organization::factory()->create();
        $this->warehouse = $this->warehouseIn($this->org);
        $this->material = $this->material();
    }

    private function receive(int $quantity, ?Warehouse $warehouse = null, ?Material $material = null): StockDocument
    {
        return app(StockService::class)->post(StockDocumentType::Receipt, ['warehouse_id' => ($warehouse ?? $this->warehouse)->id],
            [['material_id' => ($material ?? $this->material)->id, 'quantity' => $quantity, 'unit_cost' => 1000]],
            User::factory()->create());
    }

    /** @param  array<string, mixed>  $extra */
    private function document(string $type, int $quantity, array $extra = []): array
    {
        return ['type' => $type, 'warehouse_id' => $this->warehouse->id,
            'lines' => [['material_id' => $this->material->id, 'quantity' => $quantity, 'unit_cost' => 1000]], ...$extra];
    }

    public function test_receipt_is_posted_into_a_warehouse_in_scope(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $this->postJson('/api/v1/stock-documents', $this->document('receipt', 5, ['supplier' => 'Kanstovar', 'supplier_document' => 'Faktura 17']))
            ->assertCreated()
            ->assertJsonPath('data.type', 'receipt')
            ->assertJsonPath('data.total_amount', '5000.00')
            ->assertJsonPath('data.lines.0.material.name', $this->material->name)
            ->assertJsonPath('data.movements.0.balance_after', '5.000');

        $foreign = $this->warehouseIn($this->other);
        $this->postJson('/api/v1/stock-documents', [...$this->document('receipt', 1), 'warehouse_id' => $foreign->id])
            ->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
        $this->postJson('/api/v1/stock-documents', $this->document('receipt', 1, ['document_date' => now()->addDays(3)->toDateString()]))
            ->assertStatus(422)->assertJsonValidationErrors('document_date');

        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));
        $this->postJson('/api/v1/stock-documents', $this->document('receipt', 1))->assertForbidden();
    }

    public function test_each_document_type_needs_its_permission(): void
    {
        $this->receive(10);
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org));
        $this->postJson('/api/v1/stock-documents', $this->document('receipt', 1))->assertForbidden();

        $this->actingAs($this->makeUser(Roles::ACCOUNTANT, $this->org));
        $this->postJson('/api/v1/stock-documents', $this->document('issue', 1, ['reason' => 'Printer']))->assertCreated();
        $target = $this->warehouseIn($this->org);
        $this->postJson('/api/v1/stock-documents', $this->document('transfer', 1, ['target_warehouse_id' => $target->id]))->assertForbidden();

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $this->postJson('/api/v1/stock-documents', $this->document('adjustment', -1, ['reason' => 'Singan']))->assertForbidden();

        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org));
        $this->postJson('/api/v1/stock-documents', $this->document('adjustment', -1))->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson('/api/v1/stock-documents', $this->document('adjustment', -1, ['reason' => 'Singan']))->assertCreated();
    }

    public function test_issue_checks_recipient_device_reason_and_stock(): void
    {
        $this->seedAssets();
        $this->receive(3);
        $user = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org);
        $this->grantAccess($user, $this->other);
        $this->actingAs($user);
        $employee = Employee::factory()->create(['organization_id' => $this->other->id]);
        $printer = $this->assetIn($this->other, $user, ['name' => 'HP LaserJet']);
        $foreignAsset = $this->assetIn($this->org, $user);

        $this->postJson('/api/v1/stock-documents', $this->document('issue', 1))->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson('/api/v1/stock-documents', $this->document('issue', 1, ['reason' => 'x', 'recipient_employee_id' => $employee->id]))
            ->assertStatus(422)->assertJsonValidationErrors('recipient_employee_id');
        $this->postJson('/api/v1/stock-documents', $this->document('issue', 1, [
            'reason' => 'x', 'recipient_organization_id' => $this->other->id, 'asset_id' => $foreignAsset->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('asset_id');

        $this->postJson('/api/v1/stock-documents', $this->document('issue', 1, [
            'reason' => 'Kartrij almashtirish', 'recipient_organization_id' => $this->other->id,
            'recipient_employee_id' => $employee->id, 'asset_id' => $printer->id,
        ]))->assertCreated()
            ->assertJsonPath('data.recipient_organization.id', $this->other->id)
            ->assertJsonPath('data.recipient_employee.id', $employee->id)
            ->assertJsonPath('data.asset.inventory_number', $printer->inventory_number);

        $this->postJson('/api/v1/stock-documents', $this->document('issue', 5, ['reason' => 'x']))
            ->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('shortages.0.available', '2.000');
    }

    public function test_transfer_target_must_be_in_scope_and_both_sides_see_the_document(): void
    {
        $this->receive(5);
        $outside = $this->warehouseIn($this->other);
        $mine = $this->warehouseIn($this->org, ['name' => 'Filial ombori']);
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $this->postJson('/api/v1/stock-documents', $this->document('transfer', 1, ['target_warehouse_id' => $outside->id]))
            ->assertStatus(422)->assertJsonValidationErrors('target_warehouse_id');
        $id = $this->postJson('/api/v1/stock-documents', $this->document('transfer', 2, ['target_warehouse_id' => $mine->id]))
            ->assertCreated()->assertJsonPath('data.target_warehouse.id', $mine->id)->json('data.id');

        $this->getJson("/api/v1/stock-documents/{$id}")->assertOk()->assertJsonCount(2, 'data.movements');
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->other));
        $this->getJson("/api/v1/stock-documents/{$id}")->assertNotFound();
        $this->getJson('/api/v1/stock-documents')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_document_list_filters_and_return_links(): void
    {
        $paper = $this->material(['name' => 'A4 qog\'oz']);
        $this->receive(5);
        $this->receive(5, null, $paper);
        $user = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->actingAs($user);
        $issue = $this->postJson('/api/v1/stock-documents', $this->document('issue', 2, ['reason' => 'x']))->json('data.id');
        $this->postJson('/api/v1/stock-documents', $this->document('return', 1, ['related_document_id' => $issue]))->assertCreated();

        $this->getJson('/api/v1/stock-documents')->assertJsonPath('meta.total', 4);
        $this->getJson('/api/v1/stock-documents?filter[type]=issue,return')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/stock-documents?filter[material]='.$paper->id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/stock-documents?filter[warehouse]='.$this->warehouse->id.'&filter[date_from]='.now()->toDateString())->assertJsonPath('meta.total', 4);
        $this->getJson('/api/v1/stock-documents?filter[date_to]='.now()->subDay()->toDateString())->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/stock-documents/{$issue}")->assertOk()->assertJsonCount(1, 'data.returns');
    }

    public function test_balances_are_grouped_scoped_and_flag_low_stock(): void
    {
        $this->material->update(['min_quantity' => 10]);
        $batched = $this->material(['name' => 'Batareya', 'track_batches' => true, 'track_expiry' => true]);
        $this->receive(4);
        app(StockService::class)->post(StockDocumentType::Receipt, ['warehouse_id' => $this->warehouse->id], [
            ['material_id' => $batched->id, 'quantity' => 2, 'batch' => 'B-OLD', 'expires_at' => now()->addDays(10)->toDateString()],
            ['material_id' => $batched->id, 'quantity' => 3, 'batch' => 'B-NEW', 'expires_at' => now()->addYear()->toDateString()],
        ], User::factory()->create());
        $this->receive(7, $this->warehouseIn($this->other));
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $rows = $this->getJson('/api/v1/stock-balances')->assertOk()->json('data');
        $this->assertCount(2, $rows);
        $battery = collect($rows)->firstWhere('material.id', $batched->id);
        $this->assertSame('5.000', $battery['quantity']);
        $this->assertSame(['B-OLD', 'B-NEW'], array_column($battery['lots'], 'batch'));
        $this->assertTrue(collect($rows)->firstWhere('material.id', $this->material->id)['is_low']);

        $this->getJson('/api/v1/stock-balances?filter[low]=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.material.id', $this->material->id);
        $this->getJson('/api/v1/stock-balances?filter[expiring]=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.material.id', $batched->id);
        $this->getJson("/api/v1/stock-availability?warehouse_id={$this->warehouse->id}&material_id={$batched->id}")
            ->assertOk()->assertJsonPath('data.total', '5.000')->assertJsonPath('data.lots.0.batch', 'B-OLD');
        $foreign = $this->warehouseIn($this->other);
        $this->getJson("/api/v1/stock-availability?warehouse_id={$foreign->id}&material_id={$batched->id}")->assertStatus(422);
    }

    public function test_movement_journal_is_scoped_and_filtered(): void
    {
        $this->receive(4);
        $this->receive(6, $this->warehouseIn($this->other));
        $this->actingAs($this->makeUser(Roles::AUDITOR, $this->org));

        $this->getJson('/api/v1/stock-movements')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quantity', '4.000')
            ->assertJsonPath('data.0.document.type', 'receipt');
        $this->getJson('/api/v1/stock-movements?filter[type]=issue')->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/stock-documents', $this->document('receipt', 1))->assertForbidden();
    }

    public function test_scanned_document_is_attached_and_downloaded_only_in_scope(): void
    {
        Storage::fake('local');
        $doc = $this->receive(1);
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $uuid = $this->post("/api/v1/stock-documents/{$doc->id}/file", ['file' => UploadedFile::fake()->createWithContent('faktura.pdf', SafeUploadTest::pdf())],
            ['Accept' => 'application/json'])->assertOk()->json('data.file.uuid');
        $this->get("/api/v1/files/{$uuid}")->assertOk();

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->other));
        $this->get("/api/v1/files/{$uuid}", ['Accept' => 'application/json'])->assertNotFound();
    }
}
