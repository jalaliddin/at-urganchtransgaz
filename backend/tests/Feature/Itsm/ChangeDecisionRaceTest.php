<?php

namespace Tests\Feature\Itsm;

use App\Exceptions\InvalidTransitionException;
use App\Models\Change;
use App\Models\Organization;
use App\Models\User;
use App\Services\Changes\ChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Two approvers decide one change on separate MySQL connections (committed fixtures). B has already read the change
 * inside its transaction when A's approval commits; B's rejection must be refused, leaving one decision.
 */
class ChangeDecisionRaceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        foreach (['aux', 'aux_a', 'aux_b'] as $name) {
            config(["database.connections.{$name}" => config('database.connections.'.config('database.default'))]);
        }
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection(config('database.default'));
        $aux = DB::connection('aux');
        if (isset($this->ids['change'])) {
            $aux->table('audit_logs')->where('auditable_type', 'change')->where('auditable_id', $this->ids['change'])->delete();
            $aux->table('change_histories')->where('change_id', $this->ids['change'])->delete();
            $aux->table('changes')->where('id', $this->ids['change'])->delete();
            $aux->table('users')->whereIn('id', [$this->ids['requester'], $this->ids['a'], $this->ids['b']])->delete();
            $aux->table('organizations')->where('id', $this->ids['organization'])->delete();
        }
        foreach (['aux_b', 'aux_a', 'aux'] as $name) {
            DB::connection($name)->disconnect();
        }
        parent::tearDown();
    }

    public function test_a_decision_committed_after_the_snapshot_blocks_the_second_one(): void
    {
        $this->committedFixture();
        $service = app(ChangeService::class);
        $change = fn () => Change::on('aux')->withoutGlobalScopes()->findOrFail($this->ids['change']);

        $b = DB::connection('aux_b');
        $b->beginTransaction();
        $b->table('changes')->where('id', $this->ids['change'])->value('status');

        DB::setDefaultConnection('aux_a');
        $service->decide($change(), true, 'Mayli', User::on('aux')->findOrFail($this->ids['a']));

        DB::setDefaultConnection('aux_b');
        try {
            $service->decide($change(), false, 'Yo\'q', User::on('aux')->findOrFail($this->ids['b']));
            $refused = false;
        } catch (InvalidTransitionException) {
            $refused = true;
        } finally {
            $b->rollBack();
        }

        $this->assertTrue($refused, 'the second decision must be refused');
        $this->assertSame('approved', DB::connection('aux')->table('changes')->where('id', $this->ids['change'])->value('status'));
    }

    private function committedFixture(): void
    {
        $aux = DB::connection('aux');
        $organization = Organization::factory()->connection('aux')->create();
        $user = fn () => User::factory()->connection('aux')->create(['organization_id' => $organization->id])->id;
        $this->ids = ['organization' => $organization->id, 'requester' => $user(), 'a' => $user(), 'b' => $user()];
        $this->ids['change'] = $aux->table('changes')->insertGetId([
            'number' => 'CHG-RACE-'.getmypid(), 'organization_id' => $organization->id, 'title' => 'Race', 'type' => 'normal',
            'category' => 'network', 'risk' => 'low', 'status' => 'pending_approval', 'created_by' => $this->ids['requester'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
