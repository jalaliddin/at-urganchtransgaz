<?php

namespace Tests\Feature\Telecom;

use App\Models\Document;
use App\Models\Organization;
use App\Models\ServiceContract;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTelecom;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ServiceRegistryTest extends TestCase
{
    use CreatesTelecom, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $it;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
    }

    /** @param  array<string, mixed>  $extra */
    private function contract(array $extra = []): int
    {
        return $this->actingAs($this->head)->postJson('/api/v1/service-contracts', [
            'organization_id' => $this->branch->id, 'provider_id' => $this->provider()->id, 'number' => '45/2026',
            'subject' => 'Internet xizmati', 'signed_at' => '2026-01-10', 'valid_from' => '2026-01-10', 'valid_to' => '2026-12-31', ...$extra,
        ])->assertCreated()->json('data.id');
    }

    public function test_contracts_are_kept_per_organization_with_a_computed_status(): void
    {
        $id = $this->contract();
        $this->postJson('/api/v1/service-contracts', ['organization_id' => $this->branch->id, 'provider_id' => $this->provider()->id, 'number' => '45/2026'])
            ->assertStatus(422)->assertJsonValidationErrors('number');
        $this->postJson('/api/v1/service-contracts', ['organization_id' => Organization::factory()->create()->id, 'provider_id' => $this->provider()->id, 'number' => '46/2026'])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->postJson('/api/v1/service-contracts', ['organization_id' => $this->branch->id, 'provider_id' => $this->provider()->id, 'number' => '47/2026',
            'valid_from' => '2026-05-01', 'valid_to' => '2026-04-01'])->assertStatus(422)->assertJsonValidationErrors('valid_to');
        $old = $this->contract(['number' => '12/2024', 'valid_from' => '2024-01-01', 'valid_to' => '2024-12-31']);

        $this->getJson("/api/v1/service-contracts/{$id}")->assertOk()->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.provider.name', 'Sarkor Telecom');
        $this->getJson("/api/v1/service-contracts/{$old}")->assertOk()->assertJsonPath('data.status', 'expired');
        $this->getJson('/api/v1/service-contracts?filter[status]=active')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->putJson("/api/v1/service-contracts/{$id}", ['terminated_at' => '2026-11-01'])->assertOk()->assertJsonPath('data.status', 'terminated');

        $this->actingAs($this->it);
        $this->getJson('/api/v1/service-contracts')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/v1/service-contracts', ['organization_id' => $this->branch->id, 'provider_id' => $this->provider()->id, 'number' => '48/2026'])->assertForbidden();
        $foreign = ServiceContract::query()->create(['organization_id' => Organization::factory()->create()->id, 'provider_id' => $this->provider()->id, 'number' => 'X-1']);
        $this->getJson("/api/v1/service-contracts/{$foreign->id}")->assertNotFound();
    }

    public function test_services_belong_to_an_organization_and_its_own_contracts(): void
    {
        $contract = $this->contract();
        $otherProvider = $this->provider('Uztelecom');
        $payload = ['organization_id' => $this->branch->id, 'provider_id' => $this->provider()->id, 'contract_id' => $contract, 'type' => 'internet',
            'name' => 'Internet 100 Mbit/s', 'address' => 'Urganch, Al-Xorazmiy 1', 'identifier' => 'UZ-77812', 'tariff' => '100 Mbit/s',
            'fee' => '1500000', 'billing_cycle' => 'monthly', 'due_day' => 10, 'started_at' => '2026-01-10'];

        $this->actingAs($this->head);
        $id = $this->postJson('/api/v1/it-services', $payload)->assertCreated()
            ->assertJsonPath('data.fee', '1500000.00')->assertJsonPath('data.contract.number', '45/2026')->json('data.id');
        $this->postJson('/api/v1/it-services', [...$payload, 'provider_id' => $otherProvider->id])->assertStatus(422)->assertJsonValidationErrors('contract_id');
        $this->postJson('/api/v1/it-services', [...$payload, 'organization_id' => $this->center->id])->assertStatus(422)->assertJsonValidationErrors('contract_id');
        $this->postJson('/api/v1/it-services', [...$payload, 'due_day' => 31])->assertStatus(422)->assertJsonValidationErrors('due_day');
        $this->postJson('/api/v1/it-services', [...$payload, 'type' => 'radio'])->assertStatus(422)->assertJsonValidationErrors('type');

        $this->getJson('/api/v1/it-services?filter[type]=internet&filter[search]=77812')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/it-services?filter[provider]={$otherProvider->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/v1/it-services/{$id}", ['fee' => '1650000.50', 'is_active' => false])->assertOk()
            ->assertJsonPath('data.fee', '1650000.50')->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/service-contracts/{$contract}")->assertStatus(409);

        $this->actingAs($this->it);
        $this->getJson("/api/v1/it-services/{$id}")->assertOk();
        $this->putJson("/api/v1/it-services/{$id}", ['fee' => '1'])->assertForbidden();
        $foreign = $this->serviceIn(Organization::factory()->create());
        $this->getJson("/api/v1/it-services/{$foreign->id}")->assertNotFound();
    }

    public function test_documents_link_to_visible_contracts_and_sim_cards(): void
    {
        $contract = $this->contract();
        $card = $this->simIn($this->branch);
        $foreign = $this->simIn(Organization::factory()->create());
        $document = Document::create(['organization_id' => $this->branch->id, 'type' => 'contract', 'title' => 'Shartnoma 45/2026']);

        $this->actingAs($this->it);
        $this->postJson("/api/v1/documents/{$document->id}/links", ['type' => 'service_contract', 'id' => $contract])
            ->assertCreated()->assertJsonPath('data.target.number', '45/2026');
        $this->postJson("/api/v1/documents/{$document->id}/links", ['type' => 'sim_card', 'id' => $card->id])
            ->assertCreated()->assertJsonPath('data.target.number', $card->phone_number);
        $this->postJson("/api/v1/documents/{$document->id}/links", ['type' => 'sim_card', 'id' => $foreign->id])->assertNotFound();
        $this->getJson("/api/v1/service-contracts/{$contract}/documents")->assertOk()->assertJsonPath('data.0.id', $document->id);
        $this->getJson("/api/v1/sim-cards/{$card->id}/documents")->assertOk()->assertJsonPath('data.0.id', $document->id);
    }
}
