<?php

namespace Tests\Feature\Software;

use App\Models\Asset;
use App\Models\Organization;
use App\Models\Software;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Services\Software\SoftwareInstallations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Two links to the last free seat on separate MySQL connections (committed fixtures), interleaved the way
 * a real race runs: B has already read inside its transaction (REPEATABLE READ snapshot) when A commits.
 * B must still see A's link when it counts the seats.
 */
class LicenseSeatRaceTest extends TestCase
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
        if (isset($this->ids['license'])) {
            $installations = $aux->table('software_installations')->whereIn('asset_id', [$this->ids['asset_a'], $this->ids['asset_b']]);
            $aux->table('audit_logs')->where('auditable_type', 'software_installation')->whereIn('auditable_id', (clone $installations)->pluck('id'))->delete();
            $installations->delete();
            $aux->table('software_licenses')->where('id', $this->ids['license'])->delete();
            $aux->table('software')->where('id', $this->ids['software'])->delete();
            $aux->table('assets')->whereIn('id', [$this->ids['asset_a'], $this->ids['asset_b']])->delete();
            $aux->table('asset_categories')->where('id', $this->ids['category'])->delete();
            $aux->table('users')->where('id', $this->ids['user'])->delete();
            $aux->table('organizations')->where('id', $this->ids['organization'])->delete();
        }
        foreach (['aux_b', 'aux_a', 'aux'] as $name) {
            DB::connection($name)->disconnect();
        }
        parent::tearDown();
    }

    public function test_a_seat_taken_after_the_snapshot_is_still_counted(): void
    {
        $this->committedFixture();
        $service = app(SoftwareInstallations::class);
        $user = User::on('aux')->findOrFail($this->ids['user']);
        $link = ['software_id' => $this->ids['software'], 'license_id' => $this->ids['license']];

        $b = DB::connection('aux_b');
        $b->beginTransaction();
        $b->table('software')->where('id', $this->ids['software'])->value('name');

        DB::setDefaultConnection('aux_a');
        $service->add(Asset::on('aux')->withoutGlobalScopes()->findOrFail($this->ids['asset_a']), $link, $user);

        DB::setDefaultConnection('aux_b');
        try {
            $service->add(Asset::on('aux')->withoutGlobalScopes()->findOrFail($this->ids['asset_b']), $link, $user);
            $refused = false;
        } catch (ValidationException) {
            $refused = true;
        } finally {
            $b->rollBack();
        }

        $this->assertTrue($refused, 'the second link to a one-seat licence must be refused');
    }

    private function committedFixture(): void
    {
        $aux = DB::connection('aux');
        $organization = Organization::factory()->connection('aux')->create();
        $user = User::factory()->connection('aux')->create(['organization_id' => $organization->id]);
        $category = $aux->table('asset_categories')->insertGetId(['code' => 'race-'.getmypid(), 'name' => 'Race', 'created_at' => now(), 'updated_at' => now()]);
        $asset = fn (string $suffix) => $aux->table('assets')->insertGetId([
            'organization_id' => $organization->id, 'inventory_number' => 'RACE-'.getmypid().'-'.$suffix, 'name' => 'PC-'.$suffix,
            'category_id' => $category, 'status' => 'in_use', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $software = Software::on('aux')->create(['name' => 'Race Office '.getmypid(), 'category' => 'office', 'license_type' => 'commercial']);
        $license = SoftwareLicense::on('aux')->withoutGlobalScopes()->create([
            'software_id' => $software->id, 'organization_id' => $organization->id, 'seats' => 1,
        ]);
        $this->ids = ['organization' => $organization->id, 'user' => $user->id, 'category' => $category,
            'asset_a' => $asset('A'), 'asset_b' => $asset('B'), 'software' => $software->id, 'license' => $license->id];
    }
}
