<?php

namespace Tests\Feature\Telecom;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\SimCard;
use App\Models\SimHistory;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTelecom;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class SimRegistryTest extends TestCase
{
    use CreatesTelecom, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->org = Organization::factory()->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true);
    }

    public function test_providers_are_read_by_telecom_users_and_edited_by_managers(): void
    {
        $this->actingAs($this->head);
        $uzmobile = $this->postJson('/api/v1/service-providers', ['name' => 'Uzmobile', 'is_mobile_operator' => true, 'tin' => '203366731'])
            ->assertCreated()->assertJsonPath('data.is_mobile_operator', true)->json('data.id');
        $this->postJson('/api/v1/service-providers', ['name' => 'uzmobile'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/service-providers', ['name' => 'Sarkor Telecom'])->assertCreated();
        $this->getJson('/api/v1/service-providers?filter[mobile]=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Uzmobile');

        $this->simIn($this->org, ['provider_id' => $uzmobile]);
        $this->deleteJson("/api/v1/service-providers/{$uzmobile}")->assertStatus(409);

        $this->actingAs($this->makeUser(Roles::ACCOUNTANT, $this->org));
        $this->getJson('/api/v1/service-providers')->assertOk();
        $this->postJson('/api/v1/service-providers', ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));
        $this->getJson('/api/v1/service-providers')->assertForbidden();
    }

    public function test_cards_are_registered_with_a_normalised_number_and_a_masked_iccid(): void
    {
        $beeline = $this->operator();
        $this->actingAs($this->head);
        $payload = ['organization_id' => $this->org->id, 'provider_id' => $beeline->id, 'phone_number' => '+998 90 123-45-67',
            'iccid' => '8999 8011 2345 6789 0123', 'tariff' => 'Korporativ 50', 'monthly_fee' => '45000', 'account_number' => 'K-778'];
        $response = $this->postJson('/api/v1/sim-cards', $payload)->assertCreated()
            ->assertJsonPath('data.phone_number', '998901234567')->assertJsonPath('data.status', 'in_stock')
            ->assertJsonPath('data.iccid_masked', '••••••••0123')->assertJsonPath('data.monthly_fee', '45000.00');
        $id = $response->json('data.id');
        $this->assertStringNotContainsString('89998011234567890123', $response->getContent() ?: '');

        $this->postJson('/api/v1/sim-cards', [...$payload, 'iccid' => null])->assertStatus(422)->assertJsonValidationErrors('phone_number');
        $this->postJson('/api/v1/sim-cards', [...$payload, 'phone_number' => '901234560'])->assertStatus(422)->assertJsonValidationErrors('iccid');
        $this->postJson('/api/v1/sim-cards', [...$payload, 'phone_number' => '12345', 'iccid' => null])->assertStatus(422)->assertJsonValidationErrors('phone_number');
        $this->postJson('/api/v1/sim-cards', [...$payload, 'phone_number' => '901234561', 'iccid' => '123'])->assertStatus(422)->assertJsonValidationErrors('iccid');
        $this->postJson('/api/v1/sim-cards', [...$payload, 'phone_number' => '901234562', 'iccid' => null, 'provider_id' => $this->provider()->id])
            ->assertStatus(422)->assertJsonValidationErrors('provider_id');

        $list = $this->getJson('/api/v1/sim-cards')->assertOk()->assertJsonCount(1, 'data');
        $this->assertStringNotContainsString('89998011234567890123', $list->getContent() ?: '');
        $this->getJson('/api/v1/sim-cards?filter[search]=89998011234567890123')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/sim-cards?filter[search]=1234567')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/sim-cards?filter[search]=7777777')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/sim-cards?filter[provider]={$beeline->id}&filter[status]=in_stock")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/sim-cards?filter[status]=returned,in_stock')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/sim-cards?filter[status]=active,blocked')->assertOk()->assertJsonCount(0, 'data');

        $show = $this->getJson("/api/v1/sim-cards/{$id}")->assertOk()->assertJsonPath('data.history.0.event', 'registered');
        $this->assertStringNotContainsString('89998011234567890123', $show->getContent() ?: '');

        $this->postJson("/api/v1/sim-cards/{$id}/reveal")->assertOk()->assertJsonPath('data.iccid', '89998011234567890123');
        $this->assertTrue(AuditLog::query()->where('event', 'secret_revealed')->where('auditable_type', 'sim_card')->exists());
        $this->assertFalse(AuditLog::query()->where('new_values', 'like', '%89998011234567890123%')->exists());
        $this->assertFalse(SimHistory::query()->where('payload', 'like', '%89998011234567890123%')->exists());

        $this->putJson("/api/v1/sim-cards/{$id}", ['tariff' => 'Korporativ 100', 'monthly_fee' => '65000.5'])->assertOk()
            ->assertJsonPath('data.tariff', 'Korporativ 100')->assertJsonPath('data.monthly_fee', '65000.50');
        $this->assertSame('updated', SimHistory::query()->where('sim_card_id', $id)->latest('id')->value('event')->value);
    }

    public function test_cards_stay_inside_the_users_organizations(): void
    {
        $foreignOrg = Organization::factory()->create();
        $foreign = $this->simIn($foreignOrg);
        $own = $this->simIn($this->org);
        $it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);

        $this->actingAs($it);
        $this->getJson('/api/v1/sim-cards')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson("/api/v1/sim-cards/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/sim-cards/{$own->id}")->assertOk();
        $this->postJson('/api/v1/sim-cards', ['organization_id' => $this->org->id, 'provider_id' => $this->operator()->id, 'phone_number' => '901112233'])->assertForbidden();
        $this->postJson("/api/v1/sim-cards/{$own->id}/reveal")->assertForbidden();

        $this->actingAs($this->head);
        $this->postJson('/api/v1/sim-cards', ['organization_id' => $foreignOrg->id, 'provider_id' => $this->operator()->id, 'phone_number' => '901112233'])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->putJson("/api/v1/sim-cards/{$foreign->id}", ['tariff' => 'X'])->assertNotFound();
    }

    public function test_a_card_registered_by_mistake_can_be_deleted_only_while_unused(): void
    {
        $this->actingAs($this->head);
        $card = $this->simIn($this->org);
        $this->deleteJson("/api/v1/sim-cards/{$card->id}")->assertOk();
        $this->assertNull(SimCard::query()->find($card->id));
    }
}
