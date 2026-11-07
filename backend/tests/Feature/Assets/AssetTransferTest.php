<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Assets\AssetTransferIncomingNotification;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AssetTransferTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $a;

    private Organization $b;

    private User $itA;

    private User $itB;

    private Asset $pc;

    private Asset $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedAssets();
        $this->a = Organization::factory()->create(['name' => 'Urganch GTS']);
        $this->b = Organization::factory()->create(['name' => 'Xiva GTS']);
        $this->itA = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);
        $this->itB = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->b);
        $this->pc = $this->assetIn($this->a, $this->itA, ['inventory_number' => 'A-PC']);
        $this->monitor = $this->assetIn($this->a, $this->itA, ['inventory_number' => 'A-MON', 'category_id' => $this->assetCategory('monitor')->id]);
    }

    private function draft(): int
    {
        return $this->actingAs($this->itA)->postJson('/api/v1/asset-transfers', [
            'from_organization_id' => $this->a->id, 'to_organization_id' => $this->b->id,
            'note' => 'Xiva filialiga', 'asset_ids' => [$this->pc->id, $this->monitor->id],
        ])->assertCreated()->json('data.id');
    }

    public function test_full_flow_moves_assets_to_the_receiving_organization(): void
    {
        Notification::fake();
        $employee = Employee::factory()->create(['organization_id' => $this->a->id]);
        $this->actingAs($this->itA)->postJson("/api/v1/assets/{$this->pc->id}/assign", ['employee_id' => $employee->id])->assertOk();

        $id = $this->draft();
        $transfer = $this->getJson("/api/v1/asset-transfers/{$id}")->assertOk()
            ->assertJsonPath('data.status', 'draft')->assertJsonCount(2, 'data.items')->json('data');
        $this->assertMatchesRegularExpression('/^TR-\d{4}-00001$/', $transfer['number']);
        $this->patchJson("/api/v1/assets/{$this->pc->id}", ['room' => '1'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

        $act = $this->postJson("/api/v1/asset-transfers/{$id}/send")->assertOk()->assertJsonPath('data.status', 'sent')->json('data.act.uuid');
        $this->assertStringStartsWith('%PDF', $this->get("/api/v1/files/{$act}")->assertOk()->streamedContent());
        Notification::assertSentTo($this->itB, AssetTransferIncomingNotification::class);
        $this->postJson("/api/v1/asset-transfers/{$id}/receive")->assertForbidden();

        $this->actingAs($this->itB);
        $this->get("/api/v1/files/{$act}")->assertOk();
        $this->postJson("/api/v1/asset-transfers/{$id}/receive", ['note' => 'Qabul qilindi'])->assertOk()->assertJsonPath('data.status', 'received');
        $this->getJson("/api/v1/assets/{$this->pc->id}")->assertOk()
            ->assertJsonPath('data.organization.id', $this->b->id)->assertJsonPath('data.status', 'in_stock')
            ->assertJsonPath('data.holder', null)->assertJsonPath('data.in_transfer', false);
        $this->assertDatabaseHas('asset_events', ['asset_id' => $this->pc->id, 'action' => 'transferred']);
        $this->assertDatabaseMissing('asset_assignments', ['asset_id' => $this->pc->id, 'returned_at' => null]);

        $this->actingAs($this->itA)->getJson("/api/v1/assets/{$this->pc->id}")->assertNotFound();
        $this->getJson("/api/v1/asset-transfers/{$id}")->assertOk();
    }

    public function test_item_rules(): void
    {
        $foreign = $this->assetIn($this->b, $this->itB);
        $written = $this->assetIn($this->a, $this->itA);
        $written->forceFill(['status' => 'written_off'])->save();
        $id = $this->draft();

        $this->postJson("/api/v1/asset-transfers/{$id}/items", ['asset_ids' => [$foreign->id]])->assertStatus(422)->assertJsonValidationErrors('asset_ids');
        $this->postJson("/api/v1/asset-transfers/{$id}/items", ['asset_ids' => [$written->id]])->assertStatus(422)->assertJsonValidationErrors('asset_ids');

        $other = $this->postJson('/api/v1/asset-transfers', ['from_organization_id' => $this->a->id, 'to_organization_id' => $this->b->id])->json('data.id');
        $this->postJson("/api/v1/asset-transfers/{$other}/items", ['asset_ids' => [$this->pc->id]])->assertStatus(422)->assertJsonValidationErrors('asset_ids');
        $this->postJson("/api/v1/asset-transfers/{$other}/send")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

        $this->deleteJson("/api/v1/asset-transfers/{$id}/items/{$this->monitor->id}")->assertOk();
        $this->assertNull($this->monitor->fresh()->transfer_id);
        $this->postJson('/api/v1/asset-transfers', ['from_organization_id' => $this->a->id, 'to_organization_id' => $this->a->id])
            ->assertStatus(422)->assertJsonValidationErrors('to_organization_id');
        $this->postJson('/api/v1/asset-transfers', ['from_organization_id' => $this->b->id, 'to_organization_id' => $this->a->id])
            ->assertStatus(422)->assertJsonValidationErrors('from_organization_id');
    }

    public function test_receiver_cannot_change_a_draft_and_outsiders_see_nothing(): void
    {
        $id = $this->draft();

        $this->actingAs($this->itB);
        $this->getJson("/api/v1/asset-transfers/{$id}")->assertOk();
        $this->postJson("/api/v1/asset-transfers/{$id}/items", ['asset_ids' => [$this->pc->id]])->assertForbidden();
        $this->postJson("/api/v1/asset-transfers/{$id}/send")->assertForbidden();
        $this->postJson("/api/v1/asset-transfers/{$id}/cancel")->assertForbidden();
        $this->postJson("/api/v1/asset-transfers/{$id}/receive")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()));
        $this->getJson("/api/v1/asset-transfers/{$id}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/asset-transfers')->assertOk()->json('data'));
    }

    public function test_cancel_releases_assets_but_not_after_receipt(): void
    {
        $id = $this->draft();
        $this->postJson("/api/v1/asset-transfers/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertNull($this->pc->fresh()->transfer_id);

        $second = $this->draft();
        $this->postJson("/api/v1/asset-transfers/{$second}/send")->assertOk();
        $this->actingAs($this->itB)->postJson("/api/v1/asset-transfers/{$second}/receive")->assertOk();
        $this->actingAs($this->itA)->postJson("/api/v1/asset-transfers/{$second}/cancel")->assertStatus(422);
    }

    public function test_list_shows_direction(): void
    {
        $this->draft();

        $this->actingAs($this->itA);
        $this->assertSame('outgoing', $this->getJson('/api/v1/asset-transfers')->json('data.0.direction'));
        $this->actingAs($this->itB);
        $this->assertSame('incoming', $this->getJson('/api/v1/asset-transfers?filter[direction]=incoming')->json('data.0.direction'));
        $this->assertSame([], $this->getJson('/api/v1/asset-transfers?filter[direction]=outgoing')->json('data'));
    }

    public function test_destinations_list_all_active_organizations(): void
    {
        $inactive = Organization::factory()->create(['is_active' => false]);
        $this->actingAs($this->itA);

        $ids = collect($this->getJson('/api/v1/asset-transfers/destinations')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->b->id));
        $this->assertFalse($ids->contains($inactive->id));

        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));
        $this->getJson('/api/v1/asset-transfers/destinations')->assertForbidden();
    }
}
