<?php

namespace Tests\Feature\Assets;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class InventorySessionTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
        $other = Organization::factory()->create();
        $it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->accountant = $this->makeUser(Roles::ACCOUNTANT, $this->org);
        $this->assetIn($this->org, $it, ['inventory_number' => 'A-1']);
        $this->assetIn($this->org, $it, ['inventory_number' => 'A-2']);
        $this->assetIn($this->org, $it, ['inventory_number' => 'A-OLD'])->forceFill(['status' => 'written_off'])->save();
        $this->assetIn($other, $this->makeUser(Roles::SUPER_ADMIN), ['inventory_number' => 'B-1', 'name' => 'Maxfiy server']);
    }

    private function start(): int
    {
        return $this->actingAs($this->accountant)->postJson('/api/v1/inventory-sessions', [
            'organization_id' => $this->org->id, 'name' => '2026 yillik inventarizatsiya',
        ])->assertCreated()->assertJsonPath('data.expected_count', 2)->json('data.id');
    }

    public function test_scanning_marks_found_once_and_records_unexpected_codes_without_leaking(): void
    {
        $id = $this->start();
        $scan = fn (string $code) => $this->postJson("/api/v1/inventory-sessions/{$id}/scan", ['code' => $code])->assertOk();

        $scan('A-1')->assertJsonPath('data.result', 'found')->assertJsonPath('data.item.asset.inventory_number', 'A-1');
        $scan('A-1')->assertJsonPath('data.result', 'already_found')->assertJsonPath('data.session.found_count', 1);
        $scan('https://at.urtg.uz/assets/lookup/A-2')->assertJsonPath('data.result', 'found');
        $scan('B-1')->assertJsonPath('data.result', 'unexpected')->assertJsonPath('data.item.asset', null);
        $scan(' zzz-404 ')->assertJsonPath('data.result', 'unexpected');
        $scan('ZZZ-404')->assertJsonPath('data.result', 'already_scanned');

        $this->postJson("/api/v1/inventory-sessions/{$id}/close")->assertOk()
            ->assertJsonPath('data.status', 'closed')->assertJsonPath('data.found_count', 2)
            ->assertJsonPath('data.missing_count', 0)->assertJsonPath('data.unexpected_count', 2);
        $this->postJson("/api/v1/inventory-sessions/{$id}/scan", ['code' => 'A-1'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->assertDatabaseHas('asset_events', ['action' => 'inventory_found']);
    }

    public function test_missing_assets_are_reported_but_not_changed(): void
    {
        $id = $this->start();

        $this->postJson("/api/v1/inventory-sessions/{$id}/close")->assertOk()->assertJsonPath('data.missing_count', 2);

        $missing = $this->getJson("/api/v1/inventory-sessions/{$id}/items?filter[state]=missing")->assertOk()->json('data');
        $this->assertSame(['A-1', 'A-2'], collect($missing)->pluck('inventory_number')->sort()->values()->all());
        $statuses = collect($this->getJson('/api/v1/assets')->json('data'))->whereIn('inventory_number', ['A-1', 'A-2'])->pluck('status')->unique()->values()->all();
        $this->assertSame(['in_stock'], $statuses);
    }

    public function test_permissions_and_scope(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org));
        $this->postJson('/api/v1/inventory-sessions', ['organization_id' => $this->org->id, 'name' => 'X'])->assertForbidden();

        $id = $this->start();
        $this->postJson('/api/v1/inventory-sessions', ['organization_id' => $this->org->id, 'name' => 'Ikkinchi'])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()));
        $this->getJson("/api/v1/inventory-sessions/{$id}")->assertNotFound();
        $this->postJson("/api/v1/inventory-sessions/{$id}/scan", ['code' => 'A-1'])->assertNotFound();
    }
}
