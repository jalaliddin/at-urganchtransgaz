<?php

namespace Tests\Feature\Warehouse;

use App\Enums\StockDocumentType;
use App\Exceptions\InsufficientStockException;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Two processes issue from the same lot at the same time on separate MySQL
 * connections (committed fixtures): one succeeds, the other is refused, the
 * balance never goes below zero and no deadlock occurs.
 */
class StockConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required');
        }
        foreach (['aux', 'aux_a', 'aux_b'] as $name) {
            config(["database.connections.{$name}" => config('database.connections.'.config('database.default'))]);
        }
    }

    protected function tearDown(): void
    {
        $aux = DB::connection('aux');
        if (isset($this->ids['warehouse'])) {
            $documents = $aux->table('stock_documents')->where('warehouse_id', $this->ids['warehouse'])->pluck('id');
            $aux->table('stock_movements')->whereIn('document_id', $documents)->delete();
            $aux->table('stock_document_lines')->whereIn('document_id', $documents)->delete();
            $aux->table('stock_documents')->whereIn('id', $documents)->delete();
            $aux->table('stock_balances')->where('warehouse_id', $this->ids['warehouse'])->delete();
            $aux->table('warehouses')->where('id', $this->ids['warehouse'])->delete();
            $aux->table('materials')->where('id', $this->ids['material'])->delete();
            $aux->table('material_categories')->where('id', $this->ids['category'])->delete();
            $aux->table('units')->where('id', $this->ids['unit'])->delete();
            $aux->table('audit_logs')->where('user_id', $this->ids['user'])->delete();
            $aux->table('users')->where('id', $this->ids['user'])->delete();
            $aux->table('organizations')->where('id', $this->ids['organization'])->delete();
            $aux->table('number_sequences')->where('key', 'like', 'stock:%')->delete();
        }
        $aux->disconnect();
        parent::tearDown();
    }

    public function test_parallel_issues_never_drive_a_lot_negative(): void
    {
        [$warehouse, $material, $user] = $this->committedFixture();
        DB::connection('aux')->table('stock_balances')->insert([
            'warehouse_id' => $warehouse->id, 'material_id' => $material->id, 'batch' => '', 'quantity' => 10, 'unit_cost' => 5,
        ]);

        $dir = sys_get_temp_dir().'/stock-race-'.getmypid();
        @mkdir($dir);
        $pids = [];
        foreach (['aux_a', 'aux_b'] as $connection) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $this->work($connection, "{$dir}/{$connection}.txt", $warehouse, $material, $user);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $results = [];
        foreach (['aux_a', 'aux_b'] as $connection) {
            $results[] = trim((string) @file_get_contents("{$dir}/{$connection}.txt"));
            @unlink("{$dir}/{$connection}.txt");
        }
        @rmdir($dir);
        sort($results);

        $this->assertSame(['insufficient', 'ok'], $results);
        $this->assertSame('4.000', DB::connection('aux')->table('stock_balances')->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertSame(1, DB::connection('aux')->table('stock_documents')->where('warehouse_id', $warehouse->id)->count());
    }

    /** @return array{Warehouse, Material, User} */
    private function committedFixture(): array
    {
        $organization = Organization::factory()->connection('aux')->create();
        $user = User::factory()->connection('aux')->create(['organization_id' => $organization->id]);
        $unit = Unit::on('aux')->create(['code' => 'race-'.getmypid(), 'name' => 'dona']);
        $category = MaterialCategory::on('aux')->create(['name' => 'Race']);
        $material = Material::on('aux')->create(['code' => 'RACE-'.getmypid(), 'name' => 'Race', 'category_id' => $category->id, 'unit_id' => $unit->id]);
        $warehouse = Warehouse::on('aux')->withoutGlobalScopes()->create(['organization_id' => $organization->id, 'name' => 'Race']);
        $this->ids = ['organization' => $organization->id, 'user' => $user->id, 'unit' => $unit->id,
            'category' => $category->id, 'material' => $material->id, 'warehouse' => $warehouse->id];

        return [$warehouse, $material, $user];
    }

    /** Child process: never returns; exits without running destructors on the parent's shared connection. */
    private function work(string $connection, string $file, Warehouse $warehouse, Material $material, User $user): never
    {
        try {
            DB::setDefaultConnection($connection);
            app(StockService::class)->post(StockDocumentType::Issue, ['warehouse_id' => $warehouse->id],
                [['material_id' => $material->id, 'quantity' => 6]], $user);
            file_put_contents($file, 'ok');
        } catch (Throwable $e) {
            file_put_contents($file, $e instanceof InsufficientStockException ? 'insufficient' : 'ERROR '.$e::class.': '.$e->getMessage());
        }
        posix_kill(getmypid(), SIGKILL);
        exit(0);
    }
}
