<?php

namespace Tests\Feature\Printers;

use App\Models\Asset;
use App\Models\Organization;
use App\Models\User;
use App\Services\Printers\PrinterCounters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Two readings of one printer on separate MySQL connections (committed fixtures). B has already read inside its
 * transaction (REPEATABLE READ snapshot) when A commits a later reading; B's back-dated reading above A's must be refused.
 */
class PrinterCounterRaceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

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
        if (isset($this->ids['asset'])) {
            $aux->table('printer_counters')->where('asset_id', $this->ids['asset'])->delete();
            $aux->table('assets')->where('id', $this->ids['asset'])->delete();
            $aux->table('asset_categories')->where('id', $this->ids['category'])->delete();
            $aux->table('users')->where('id', $this->ids['user'])->delete();
            $aux->table('organizations')->where('id', $this->ids['organization'])->delete();
        }
        foreach (['aux_b', 'aux_a', 'aux'] as $name) {
            DB::connection($name)->disconnect();
        }
        parent::tearDown();
    }

    public function test_a_reading_committed_after_the_snapshot_still_bounds_a_back_dated_one(): void
    {
        $this->committedFixture();
        $service = app(PrinterCounters::class);
        $user = User::on('aux')->findOrFail($this->ids['user']);
        $printer = fn () => Asset::on('aux')->withoutGlobalScopes()->findOrFail($this->ids['asset']);

        $b = DB::connection('aux_b');
        $b->beginTransaction();
        $b->table('printer_counters')->where('asset_id', $this->ids['asset'])->count();

        DB::setDefaultConnection('aux_a');
        $service->record($printer(), ['read_at' => '2026-11-01', 'total' => 1200], $user);

        DB::setDefaultConnection('aux_b');
        try {
            $service->record($printer(), ['read_at' => '2026-10-15', 'total' => 1500], $user);
            $refused = false;
        } catch (ValidationException) {
            $refused = true;
        } finally {
            $b->rollBack();
        }

        $this->assertTrue($refused, 'a back-dated reading above a later committed reading must be refused');
    }

    private function committedFixture(): void
    {
        $aux = DB::connection('aux');
        $organization = Organization::factory()->connection('aux')->create();
        $user = User::factory()->connection('aux')->create(['organization_id' => $organization->id]);
        $category = $aux->table('asset_categories')->insertGetId(['code' => 'race-'.getmypid(), 'name' => 'Race', 'created_at' => now(), 'updated_at' => now()]);
        $asset = $aux->table('assets')->insertGetId([
            'organization_id' => $organization->id, 'inventory_number' => 'RACE-PR-'.getmypid(), 'name' => 'Printer',
            'category_id' => $category, 'status' => 'in_use', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $aux->table('printer_counters')->insert(['organization_id' => $organization->id, 'asset_id' => $asset, 'read_at' => '2026-10-01',
            'total' => 1000, 'is_reset' => false, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
        $this->ids = ['organization' => $organization->id, 'user' => $user->id, 'category' => $category, 'asset' => $asset];
    }
}
