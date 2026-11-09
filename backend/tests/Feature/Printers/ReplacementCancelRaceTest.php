<?php

namespace Tests\Feature\Printers;

use App\Enums\StockDocumentType;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\CartridgeReplacement;
use App\Models\Organization;
use App\Models\User;
use App\Services\Printers\CartridgeReplacements;
use App\Services\Warehouse\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two cancels of one warehouse replacement on separate MySQL connections (committed fixtures). B has already read
 * inside its transaction when A's cancel (and its return) commits; B's cancel must be refused, so the cartridge goes
 * back to stock only once.
 */
class ReplacementCancelRaceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    /** @var array<string, int> */
    private array $sequences = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['aux', 'aux_a', 'aux_b'] as $name) {
            config(["database.connections.{$name}" => config('database.connections.'.config('database.default'))]);
        }
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection(config('database.default'));
        $aux = DB::connection('aux');
        if (isset($this->ids['warehouse'])) {
            $replacements = $aux->table('cartridge_replacements')->where('asset_id', $this->ids['asset'])->pluck('id');
            $documents = $aux->table('stock_documents')->where('warehouse_id', $this->ids['warehouse'])->pluck('id');
            $aux->table('audit_logs')->where(fn ($q) => $q->where('auditable_type', 'cartridge_replacement')->whereIn('auditable_id', $replacements))
                ->orWhere(fn ($q) => $q->where('auditable_type', 'stock_document')->whereIn('auditable_id', $documents))->delete();
            $aux->table('cartridge_replacements')->whereIn('id', $replacements)->delete();
            $aux->table('stock_movements')->where('warehouse_id', $this->ids['warehouse'])->delete();
            $aux->table('stock_document_lines')->whereIn('document_id', $documents)->delete();
            $aux->table('stock_documents')->whereIn('id', $documents)->where('type', 'return')->delete();
            $aux->table('stock_documents')->whereIn('id', $documents)->delete();
            $aux->table('stock_alerts')->where('warehouse_id', $this->ids['warehouse'])->delete();
            $aux->table('stock_balances')->where('warehouse_id', $this->ids['warehouse'])->delete();
            $aux->table('cartridge_models')->where('id', $this->ids['cartridge'])->delete();
            $aux->table('materials')->where('id', $this->ids['material'])->delete();
            $aux->table('material_categories')->where('id', $this->ids['material_category'])->delete();
            $aux->table('units')->where('id', $this->ids['unit'])->delete();
            $aux->table('warehouses')->where('id', $this->ids['warehouse'])->delete();
            $aux->table('assets')->where('id', $this->ids['asset'])->delete();
            $aux->table('asset_categories')->where('id', $this->ids['category'])->delete();
            $aux->table('users')->where('id', $this->ids['user'])->delete();
            $aux->table('organizations')->where('id', $this->ids['organization'])->delete();
            $aux->table('number_sequences')->whereNotIn('key', array_keys($this->sequences))->delete();
            foreach ($this->sequences as $key => $last) {
                $aux->table('number_sequences')->where('key', $key)->update(['last_number' => $last]);
            }
        }
        foreach (['aux_b', 'aux_a', 'aux'] as $name) {
            DB::connection($name)->disconnect();
        }
        parent::tearDown();
    }

    public function test_a_cancel_committed_after_the_snapshot_blocks_the_second_cancel(): void
    {
        $replacement = $this->committedFixture();
        $service = app(CartridgeReplacements::class);
        $user = User::on('aux')->findOrFail($this->ids['user']);

        $b = DB::connection('aux_b');
        $b->beginTransaction();
        $b->table('cartridge_replacements')->where('id', $replacement)->value('cancelled_at');

        DB::setDefaultConnection('aux_a');
        $service->cancel(CartridgeReplacement::on('aux')->withoutGlobalScopes()->findOrFail($replacement), 'Xato', $user);

        DB::setDefaultConnection('aux_b');
        try {
            $service->cancel(CartridgeReplacement::on('aux')->withoutGlobalScopes()->findOrFail($replacement), 'Yana xato', $user);
            $refused = false;
        } catch (InvalidTransitionException) {
            $refused = true;
        } finally {
            $b->rollBack();
        }

        $this->assertTrue($refused, 'the second cancel must be refused');
        DB::setDefaultConnection(config('database.default'));
        $this->assertSame(1, DB::connection('aux')->table('stock_documents')->where('warehouse_id', $this->ids['warehouse'])->where('type', 'return')->count());
        $this->assertEquals(5, DB::connection('aux')->table('stock_balances')->where('warehouse_id', $this->ids['warehouse'])->value('quantity'));
    }

    private function committedFixture(): int
    {
        $aux = DB::connection('aux');
        $this->sequences = $aux->table('number_sequences')->pluck('last_number', 'key')->map(fn ($n) => (int) $n)->all();
        $pid = getmypid();
        $now = ['created_at' => now(), 'updated_at' => now()];
        $organization = Organization::factory()->connection('aux')->create();
        $user = User::factory()->connection('aux')->create(['organization_id' => $organization->id]);
        $category = $aux->table('asset_categories')->insertGetId(['code' => "race-{$pid}", 'name' => 'Race', ...$now]);
        $asset = $aux->table('assets')->insertGetId(['organization_id' => $organization->id, 'inventory_number' => "RACE-RC-{$pid}",
            'name' => 'Printer', 'category_id' => $category, 'status' => 'in_use', ...$now]);
        $materialCategory = $aux->table('material_categories')->insertGetId(['code' => "race-{$pid}", 'name' => 'Race', ...$now]);
        $unit = $aux->table('units')->insertGetId(['code' => "race-{$pid}", 'name' => 'dona', ...$now]);
        $material = $aux->table('materials')->insertGetId(['code' => "RACE-MT-{$pid}", 'name' => 'Toner', 'category_id' => $materialCategory,
            'unit_id' => $unit, ...$now]);
        $cartridge = $aux->table('cartridge_models')->insertGetId(['material_id' => $material, 'name' => 'Race toner', 'kind' => 'toner',
            'color' => 'black', ...$now]);
        $warehouse = $aux->table('warehouses')->insertGetId(['organization_id' => $organization->id, 'name' => "Race {$pid}", ...$now]);
        $this->ids = ['organization' => $organization->id, 'user' => $user->id, 'category' => $category, 'asset' => $asset,
            'material_category' => $materialCategory, 'unit' => $unit, 'material' => $material, 'cartridge' => $cartridge, 'warehouse' => $warehouse];

        DB::setDefaultConnection('aux');
        $by = User::on('aux')->findOrFail($user->id);
        app(StockService::class)->post(StockDocumentType::Receipt, ['warehouse_id' => $warehouse], [['material_id' => $material, 'quantity' => 5, 'unit_cost' => 1000]], $by);
        $replacement = app(CartridgeReplacements::class)->replace(Asset::on('aux')->withoutGlobalScopes()->findOrFail($asset), [
            'cartridge_model_id' => $cartridge, 'replaced_at' => now()->toDateString(), 'source' => 'warehouse', 'warehouse_id' => $warehouse,
        ], $by);
        DB::setDefaultConnection(config('database.default'));

        return $replacement->id;
    }
}
