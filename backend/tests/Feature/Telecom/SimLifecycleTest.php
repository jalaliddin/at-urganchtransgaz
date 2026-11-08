<?php

namespace Tests\Feature\Telecom;

use App\Enums\RequisitionDecision;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\SimAssignment;
use App\Models\SimCard;
use App\Models\SimHistory;
use App\Models\User;
use App\Services\Requisitions\RequisitionWorkflow;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesRequisitions;
use Tests\Concerns\CreatesTelecom;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class SimLifecycleTest extends TestCase
{
    use CreatesRequisitions, CreatesTelecom, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $it;

    private Employee $alisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->alisher = Employee::factory()->create(['organization_id' => $this->branch->id, 'last_name' => 'Karimov', 'first_name' => 'Alisher']);
    }

    private function act(SimCard $card, string $action, array $payload = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->head)->postJson("/api/v1/sim-cards/{$card->id}/{$action}", $payload);
    }

    public function test_every_transition_follows_the_status_rules_and_is_recorded(): void
    {
        $card = $this->simIn($this->branch);
        $department = Department::factory()->create(['organization_id' => $this->branch->id]);

        $this->act($card, 'unblock')->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->act($card, 'return')->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->act($card, 'assign', ['employee_id' => $this->alisher->id, 'department_id' => $department->id, 'assigned_at' => '2026-10-01'], $this->it)
            ->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.employee.id', $this->alisher->id)
            ->assertJsonPath('data.allocated_at', '2026-10-01');
        $this->act($card, 'assign', ['employee_id' => $this->alisher->id], $this->it)->assertStatus(422);
        $this->act($card, 'block', ['reason' => 'Telefon yo\'qolgan'], $this->it)->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->act($card, 'assign', ['employee_id' => $this->alisher->id], $this->it)->assertStatus(422);
        $this->act($card, 'unblock', [], $this->it)->assertOk()->assertJsonPath('data.status', 'active');
        $this->act($card, 'return', ['returned_at' => '2026-11-01'], $this->it)->assertOk()->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.employee', null)->assertJsonPath('data.returned_at', '2026-11-01');
        $this->act($card, 'block', [], $this->it)->assertOk()->assertJsonPath('data.status', 'blocked');
        $this->act($card, 'unblock', [], $this->it)->assertOk()->assertJsonPath('data.status', 'returned');
        $this->act($card, 'assign', ['department_id' => $department->id], $this->it)->assertOk()->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.employee', null)->assertJsonPath('data.department.id', $department->id);

        $this->act($card, 'cancel', ['reason' => 'Operator bilan shartnoma tugadi'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        foreach (['assign', 'return', 'block', 'unblock', 'transfer', 'cancel'] as $action) {
            $this->act($card, $action, ['employee_id' => $this->alisher->id, 'organization_id' => $this->center->id])->assertStatus(422);
        }
        $this->act($card, 'replace', ['iccid' => '89998011111111111111'])->assertStatus(422);

        $periods = SimAssignment::query()->where('sim_card_id', $card->id)->orderBy('id')->get();
        $this->assertCount(2, $periods);
        $this->assertSame('2026-11-01', $periods[0]->returned_at?->toDateString());
        $this->assertNotNull($periods[1]->returned_at);
        $this->assertSame(['assigned', 'blocked', 'unblocked', 'returned', 'blocked', 'unblocked', 'assigned', 'cancelled'],
            SimHistory::query()->where('sim_card_id', $card->id)->orderBy('id')->get()->map(fn ($h) => $h->event->value)->all());

        // The number of a cancelled card can go on a new card.
        $this->simIn($this->branch, ['phone_number' => $card->phone_number]);
    }

    public function test_assignment_stays_inside_the_holder_organization_and_needs_the_right_permission(): void
    {
        $card = $this->simIn($this->branch);
        $stranger = Employee::factory()->create(['organization_id' => $this->center->id]);
        $foreignDepartment = Department::factory()->create(['organization_id' => $this->center->id]);

        $this->act($card, 'assign', [], $this->it)->assertStatus(422)->assertJsonValidationErrors('employee_id');
        $this->act($card, 'assign', ['employee_id' => $stranger->id], $this->it)->assertStatus(422)->assertJsonValidationErrors('employee_id');
        $this->act($card, 'assign', ['department_id' => $foreignDepartment->id])->assertStatus(422)->assertJsonValidationErrors('department_id');
        $this->act($card, 'assign', ['employee_id' => $this->alisher->id], $this->makeUser(Roles::ORG_HEAD, $this->branch))->assertForbidden();
        $this->act($card, 'transfer', ['organization_id' => $this->center->id], $this->it)->assertForbidden();
        $this->act($card, 'cancel', [], $this->it)->assertForbidden();

        $foreign = $this->simIn(Organization::factory()->create());
        $this->act($foreign, 'assign', ['employee_id' => $this->alisher->id], $this->it)->assertNotFound();

        $this->act($card, 'assign', ['employee_id' => $this->alisher->id], $this->it)->assertOk();
        $this->actingAs($this->it)->getJson("/api/v1/employees/{$this->alisher->id}/sim-cards")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $card->id);
    }

    public function test_transfer_moves_only_free_cards_to_organizations_in_scope(): void
    {
        $card = $this->simIn($this->center);
        $outside = Organization::factory()->create();

        $this->act($card, 'transfer', ['organization_id' => $outside->id])->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->act($card, 'transfer', ['organization_id' => $this->center->id])->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->act($card, 'transfer', ['organization_id' => $this->branch->id, 'note' => 'Filial uchun'])->assertOk()
            ->assertJsonPath('data.organization.id', $this->branch->id)->assertJsonPath('data.status', 'in_stock');
        $history = SimHistory::query()->where('sim_card_id', $card->id)->latest('id')->firstOrFail();
        $this->assertSame('transferred', $history->event->value);
        $this->assertEquals(['from' => $this->center->id, 'to' => $this->branch->id, 'note' => 'Filial uchun'], $history->payload);

        $this->act($card, 'assign', ['employee_id' => $this->alisher->id], $this->it)->assertOk();
        $this->act($card, 'transfer', ['organization_id' => $this->center->id])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_replacing_the_card_keeps_the_number_and_records_only_the_last_digits(): void
    {
        $card = $this->simIn($this->branch, ['iccid' => '89998011234567890123']);
        $other = $this->simIn($this->branch, ['iccid' => '89998019999999999999']);

        $this->act($card, 'replace', ['iccid' => '8999 8019 9999 9999 9999'])->assertStatus(422)->assertJsonValidationErrors('iccid');
        $this->act($card, 'replace', ['iccid' => '12'])->assertStatus(422)->assertJsonValidationErrors('iccid');
        $this->act($card, 'replace', ['iccid' => '89998015555555554321', 'reason' => 'Shikastlangan'], $this->it)->assertForbidden();
        $this->act($card, 'replace', ['iccid' => '89998015555555554321', 'reason' => 'Shikastlangan'])->assertOk()
            ->assertJsonPath('data.iccid_masked', '••••••••4321')->assertJsonPath('data.phone_number', $card->phone_number);

        $history = SimHistory::query()->where('sim_card_id', $card->id)->latest('id')->firstOrFail();
        $this->assertEquals(['old_last4' => '0123', 'new_last4' => '4321', 'reason' => 'Shikastlangan'], $history->payload);
        $this->assertSame(SimCard::iccidHash('89998015555555554321'), $card->refresh()->iccid_hash);
        $this->assertNotSame($other->iccid_hash, $card->iccid_hash);
    }

    public function test_a_sim_requisition_is_fulfilled_with_a_free_card_moved_to_the_requesting_organization(): void
    {
        app(SettingsService::class)->update(['requisitions.head_approval' => false]);
        $staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->center, true);
        $card = $this->simIn($this->center);
        $busy = $this->simIn($this->center);
        $this->act($busy, 'assign', ['employee_id' => Employee::factory()->create(['organization_id' => $this->center->id])->id])->assertOk();

        $requisition = $this->draftFor($this->branch, $this->it, [
            ['kind' => 'sim', 'name' => 'SIM-karta (Beeline)', 'quantity' => 2],
            ['kind' => 'other', 'name' => 'Telefon apparati', 'quantity' => 1],
        ]);
        $workflow = app(RequisitionWorkflow::class);
        $workflow->submit($requisition, $this->it);
        $workflow->decide($requisition, RequisitionDecision::Approved, null, [], $this->head);
        $items = $requisition->items()->pluck('id', 'kind')->map(fn ($id) => (int) $id)->all();
        $allocate = fn (array $payload) => $this->actingAs($staff)->postJson("/api/v1/requisitions/{$requisition->id}/allocations", $payload);

        $allocate(['item_id' => $items['other'], 'type' => 'sim', 'sim_card_id' => $card->id])->assertStatus(422)->assertJsonValidationErrors('type');
        $allocate(['item_id' => $items['sim'], 'type' => 'sim', 'sim_card_id' => $busy->id])->assertStatus(422)->assertJsonValidationErrors('sim_card_id');
        $allocate(['item_id' => $items['sim'], 'type' => 'sim', 'sim_card_id' => $card->id])->assertCreated()
            ->assertJsonPath('data.status', 'partially_fulfilled')
            ->assertJsonPath('data.fulfillments.0.sim_card.phone_number', $card->phone_number);
        $allocate(['item_id' => $items['sim'], 'type' => 'sim', 'sim_card_id' => $card->id])->assertStatus(422)->assertJsonValidationErrors('sim_card_id');

        $card->refresh();
        $this->assertSame($this->branch->id, $card->organization_id);
        $history = SimHistory::query()->where('sim_card_id', $card->id)->latest('id')->firstOrFail();
        $this->assertSame('allocated', $history->event->value);
        $this->assertSame($requisition->id, $history->requisition_id);
    }
}
