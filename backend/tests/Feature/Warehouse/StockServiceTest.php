<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockCountStatus;
use App\Enums\StockDocumentType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\WarehouseCountingException;
use App\Models\AuditLog;
use App\Models\Material;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private Warehouse $warehouse;

    private User $user;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarehouse();
        $this->org = Organization::factory()->create();
        $this->warehouse = $this->warehouseIn($this->org);
        $this->user = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org);
        $this->stock = app(StockService::class);
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function postDocument(StockDocumentType $type, array $lines, array $header = []): StockDocument
    {
        return $this->stock->post($type, ['warehouse_id' => $this->warehouse->id, ...$header], $lines, $this->user);
    }

    private function quantity(Material $material, string $batch = '', ?Warehouse $warehouse = null): ?string
    {
        return StockBalance::query()->where('warehouse_id', ($warehouse ?? $this->warehouse)->id)
            ->where('material_id', $material->id)->where('batch', $batch)->value('quantity');
    }

    public function test_receipt_creates_a_lot_journal_number_and_averages_cost(): void
    {
        $material = $this->material();

        $first = $this->postDocument(StockDocumentType::Receipt, [['material_id' => $material->id, 'quantity' => 10, 'unit_cost' => 100]],
            ['supplier' => 'Office Market MChJ', 'supplier_document' => 'HF-145']);
        $this->postDocument(StockDocumentType::Receipt, [['material_id' => $material->id, 'quantity' => '10.000', 'unit_cost' => '200']]);

        $this->assertMatchesRegularExpression('/^RC-\d{4}-00001$/', $first->number);
        $this->assertSame('1000.00', $first->total_amount);
        $this->assertSame('Office Market MChJ', $first->supplier);
        $lot = StockBalance::query()->where('material_id', $material->id)->firstOrFail();
        $this->assertSame('20.000', $lot->quantity);
        $this->assertSame('150.00', $lot->unit_cost);
        $this->assertSame(['10.000', '20.000'], StockMovement::query()->orderBy('id')->pluck('balance_after')->all());
        $this->assertTrue(AuditLog::query()->where('auditable_type', 'stock_document')->where('auditable_id', $first->id)->exists());
    }

    public function test_batch_and_expiry_rules_on_receipt(): void
    {
        $batched = $this->material(['track_batches' => true, 'track_expiry' => true]);

        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Receipt, [['material_id' => $batched->id, 'quantity' => 1, 'expires_at' => '2027-01-01']]), 'lines.0.batch');
        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Receipt, [['material_id' => $batched->id, 'quantity' => 1, 'batch' => 'B1']]), 'lines.0.expires_at');

        $this->postDocument(StockDocumentType::Receipt, [['material_id' => $batched->id, 'quantity' => 1, 'batch' => 'B1', 'expires_at' => '2027-01-01']]);
        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Receipt, [['material_id' => $batched->id, 'quantity' => 1, 'batch' => 'B1', 'expires_at' => '2027-02-01']]), 'lines.0.expires_at');

        $plain = $this->material();
        $this->postDocument(StockDocumentType::Receipt, [['material_id' => $plain->id, 'quantity' => 2, 'batch' => 'ignored']]);
        $this->assertSame('2.000', $this->quantity($plain));
    }

    public function test_issue_takes_earliest_expiry_first_then_oldest_lot(): void
    {
        $material = $this->material(['track_batches' => true]);
        $this->postDocument(StockDocumentType::Receipt, [
            ['material_id' => $material->id, 'quantity' => 5, 'batch' => 'NOEXP', 'unit_cost' => 10],
            ['material_id' => $material->id, 'quantity' => 5, 'batch' => 'LATE', 'expires_at' => '2027-06-01', 'unit_cost' => 20],
            ['material_id' => $material->id, 'quantity' => 5, 'batch' => 'EARLY', 'expires_at' => '2027-01-01', 'unit_cost' => 30],
        ]);

        $issue = $this->postDocument(StockDocumentType::Issue, [['material_id' => $material->id, 'quantity' => 7]], ['reason' => 'Printerlar uchun']);

        $this->assertSame('0.000', $this->quantity($material, 'EARLY'));
        $this->assertSame('3.000', $this->quantity($material, 'LATE'));
        $this->assertSame('5.000', $this->quantity($material, 'NOEXP'));
        $this->assertSame([['EARLY', '-5.000', '30.00'], ['LATE', '-2.000', '20.00']],
            $issue->movements()->orderBy('id')->get()->map(fn ($m) => [$m->batch, $m->quantity, $m->unit_cost])->all());
        $this->assertSame('190.00', $issue->total_amount);

        $this->postDocument(StockDocumentType::Issue, [['material_id' => $material->id, 'quantity' => 1, 'batch' => 'NOEXP']]);
        $this->assertSame('4.000', $this->quantity($material, 'NOEXP'));
    }

    public function test_issue_beyond_the_balance_is_refused_and_changes_nothing(): void
    {
        $material = $this->material();
        $this->postDocument(StockDocumentType::Receipt, [['material_id' => $material->id, 'quantity' => '2.5']]);

        try {
            $this->postDocument(StockDocumentType::Issue, [
                ['material_id' => $material->id, 'quantity' => 2],
                ['material_id' => $material->id, 'quantity' => 1],
            ]);
            $this->fail('expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame([['material_id' => $material->id, 'material' => $material->name, 'batch' => null,
                'available' => '0.500', 'requested' => '1.000']], $e->shortages);
        }

        $this->assertSame('2.500', $this->quantity($material));
        $this->assertSame(1, StockDocument::query()->count());
    }

    public function test_transfer_moves_lots_with_batch_expiry_and_cost(): void
    {
        $material = $this->material(['track_batches' => true, 'track_expiry' => true]);
        $target = $this->warehouseIn($this->org);
        $this->postDocument(StockDocumentType::Receipt, [['material_id' => $material->id, 'quantity' => 4, 'batch' => 'B7', 'expires_at' => '2027-03-01', 'unit_cost' => 55]]);

        $transfer = $this->postDocument(StockDocumentType::Transfer, [['material_id' => $material->id, 'quantity' => 3]], ['target_warehouse_id' => $target->id]);

        $this->assertSame('1.000', $this->quantity($material, 'B7'));
        $moved = StockBalance::query()->where('warehouse_id', $target->id)->firstOrFail();
        $this->assertSame(['B7', '3.000', '55.00', '2027-03-01'], [$moved->batch, $moved->quantity, $moved->unit_cost, $moved->expires_at?->toDateString()]);
        $this->assertSame($this->org->id, $transfer->target_organization_id);
        $this->assertSame(2, $transfer->movements()->count());

        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Transfer, [['material_id' => $material->id, 'quantity' => 1]], ['target_warehouse_id' => $this->warehouse->id]), 'target_warehouse_id');
    }

    public function test_return_of_an_issue_is_limited_to_what_was_issued_and_keeps_its_cost(): void
    {
        $material = $this->material();
        $this->postDocument(StockDocumentType::Receipt, [['material_id' => $material->id, 'quantity' => 10, 'unit_cost' => 80]]);
        $issue = $this->postDocument(StockDocumentType::Issue, [['material_id' => $material->id, 'quantity' => 4]]);

        $return = $this->postDocument(StockDocumentType::Return, [['material_id' => $material->id, 'quantity' => 3]], ['related_document_id' => $issue->id]);
        $this->assertSame('240.00', $return->total_amount);
        $this->assertSame('9.000', $this->quantity($material));

        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Return, [['material_id' => $material->id, 'quantity' => 2]], ['related_document_id' => $issue->id]), 'lines.0.quantity');
        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Return, [['material_id' => $material->id, 'quantity' => 1]], ['related_document_id' => $return->id]), 'related_document_id');
    }

    public function test_adjustment_lines_are_signed(): void
    {
        $material = $this->material();
        $this->postDocument(StockDocumentType::Adjustment, [['material_id' => $material->id, 'quantity' => 3]], ['reason' => 'Topildi']);
        $this->postDocument(StockDocumentType::Adjustment, [['material_id' => $material->id, 'quantity' => -1]], ['reason' => 'Singan']);
        $this->assertSame('2.000', $this->quantity($material));

        $this->expectException(InsufficientStockException::class);
        $this->postDocument(StockDocumentType::Adjustment, [['material_id' => $material->id, 'quantity' => -5]], ['reason' => 'Xato']);
    }

    public function test_open_count_freezes_the_warehouse(): void
    {
        $material = $this->material();
        StockCount::query()->withoutGlobalScopes()->create([
            'organization_id' => $this->org->id, 'warehouse_id' => $this->warehouse->id, 'number' => 'SC-T-1',
            'status' => StockCountStatus::Open, 'open_key' => $this->warehouse->id,
        ]);

        $this->expectException(WarehouseCountingException::class);
        $this->postDocument(StockDocumentType::Receipt, [['material_id' => $material->id, 'quantity' => 1]]);
    }

    public function test_inactive_warehouse_and_bad_lines_are_rejected(): void
    {
        $material = $this->material();
        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Receipt, []), 'lines');
        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Receipt, [['material_id' => $material->id, 'quantity' => 0]]), 'lines.0.quantity');
        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Issue, [['material_id' => $material->id, 'quantity' => -1]]), 'lines.0.quantity');

        $this->warehouse->update(['is_active' => false]);
        $this->assertValidation(fn () => $this->postDocument(StockDocumentType::Receipt, [['material_id' => $material->id, 'quantity' => 1]]), 'warehouse_id');
    }

    private function assertValidation(callable $action, string $key): void
    {
        try {
            $action();
            $this->fail("expected a validation error on {$key}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors(), json_encode($e->errors(), JSON_UNESCAPED_UNICODE) ?: '');
        }
    }
}
