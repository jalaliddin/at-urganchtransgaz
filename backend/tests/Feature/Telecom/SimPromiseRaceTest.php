<?php

namespace Tests\Feature\Telecom;

use App\Models\Organization;
use App\Models\User;
use App\Services\Requisitions\RequisitionQueries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two fulfillers give the same SIM card to two requisitions: B has read inside its transaction (REPEATABLE READ
 * snapshot) before A's allocation commits. When B gets the card lock it must still see A's fulfilment.
 */
class SimPromiseRaceTest extends TestCase
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
        if (isset($this->ids['requisition'])) {
            $aux->table('requisition_fulfillments')->where('requisition_id', $this->ids['requisition'])->delete();
            $aux->table('requisition_items')->where('requisition_id', $this->ids['requisition'])->delete();
            $aux->table('requisitions')->where('id', $this->ids['requisition'])->delete();
            $aux->table('sim_cards')->where('id', $this->ids['card'])->delete();
            $aux->table('service_providers')->where('id', $this->ids['provider'])->delete();
            $aux->table('users')->where('id', $this->ids['user'])->delete();
            $aux->table('organizations')->where('id', $this->ids['organization'])->delete();
        }
        foreach (['aux_b', 'aux_a', 'aux'] as $name) {
            DB::connection($name)->disconnect();
        }
        parent::tearDown();
    }

    public function test_a_promise_committed_after_the_snapshot_is_still_seen(): void
    {
        $this->committedFixture();

        $b = DB::connection('aux_b');
        $b->beginTransaction();
        $b->table('requisitions')->where('id', $this->ids['requisition'])->value('status');

        DB::connection('aux_a')->table('requisition_fulfillments')->insert([
            'requisition_id' => $this->ids['requisition'], 'item_id' => $this->ids['item'], 'type' => 'sim', 'quantity' => 1,
            'sim_card_id' => $this->ids['card'], 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::setDefaultConnection('aux_b');
        try {
            $promised = RequisitionQueries::promised('sim_card_id', $this->ids['card']);
        } finally {
            $b->rollBack();
        }

        $this->assertTrue($promised, 'the card was already given to an open requisition');
    }

    private function committedFixture(): void
    {
        $aux = DB::connection('aux');
        $organization = Organization::factory()->connection('aux')->create();
        $user = User::factory()->connection('aux')->create(['organization_id' => $organization->id]);
        $provider = $aux->table('service_providers')->insertGetId(['name' => 'Race operator '.getmypid(), 'is_mobile_operator' => true, 'created_at' => now(), 'updated_at' => now()]);
        $card = $aux->table('sim_cards')->insertGetId(['organization_id' => $organization->id, 'provider_id' => $provider,
            'phone_number' => '99899'.substr((string) getmypid().'0000000', 0, 7), 'status' => 'in_stock', 'created_at' => now(), 'updated_at' => now()]);
        $requisition = $aux->table('requisitions')->insertGetId(['organization_id' => $organization->id, 'number' => 'RQ-RACE-'.getmypid(),
            'title' => 'SIM', 'status' => 'approved', 'requester_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        $item = $aux->table('requisition_items')->insertGetId(['requisition_id' => $requisition, 'kind' => 'sim', 'name' => 'SIM',
            'quantity' => 2, 'approved_quantity' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $this->ids = ['organization' => $organization->id, 'user' => $user->id, 'provider' => $provider, 'card' => $card,
            'requisition' => $requisition, 'item' => $item];
    }
}
