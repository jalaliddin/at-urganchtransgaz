<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetModel;
use App\Models\Department;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\Feature\Files\SafeUploadTest;
use Tests\TestCase;

class AssetCoreTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private Organization $other;

    private User $it;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
        $this->other = Organization::factory()->create();
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'organization_id' => $this->org->id,
            'name' => 'HP ProDesk 400 G7',
            'category_id' => $this->assetCategory()->id,
            'serial_number' => 'CZC1234XYZ',
            'hostname' => 'urg-pc-214',
            'ip_address' => '10.20.1.14',
            'mac_address' => 'aa:bb:cc:dd:ee:ff',
            'purchase_date' => '2025-03-01',
            'warranty_until' => '2028-03-01',
            'cost' => '8500000.00',
            ...$overrides,
        ];
    }

    public function test_create_with_given_or_generated_inventory_number(): void
    {
        $this->actingAs($this->it);

        $this->postJson('/api/v1/assets', $this->payload(['inventory_number' => 'INV-0001']))
            ->assertCreated()->assertJsonPath('data.inventory_number', 'INV-0001')
            ->assertJsonPath('data.status', 'in_stock')
            ->assertJsonPath('data.mac_address', 'AA:BB:CC:DD:EE:FF');
        $this->postJson('/api/v1/assets', $this->payload(['inventory_number' => 'INV-0001']))
            ->assertStatus(422)->assertJsonValidationErrors('inventory_number');
        $this->postJson('/api/v1/assets', $this->payload())->assertCreated()->assertJsonPath('data.inventory_number', 'AT-000001');
        $this->postJson('/api/v1/assets', $this->payload())->assertCreated()->assertJsonPath('data.inventory_number', 'AT-000002');
        $this->assertDatabaseHas('asset_events', ['action' => 'created']);
    }

    public function test_validation_of_network_fields_and_same_organization_references(): void
    {
        $this->actingAs($this->it);
        $foreignLocation = Location::create(['organization_id' => $this->other->id, 'name' => 'Ombor']);
        $foreignDepartment = Department::factory()->create(['organization_id' => $this->other->id]);
        $hp = Manufacturer::create(['name' => 'HP']);
        $printerModel = AssetModel::create(['manufacturer_id' => $hp->id, 'category_id' => $this->assetCategory('printer')->id, 'name' => 'LaserJet M404']);

        $this->postJson('/api/v1/assets', $this->payload([
            'ip_address' => '10.20.1.999', 'mac_address' => 'not-a-mac', 'location_id' => $foreignLocation->id,
            'department_id' => $foreignDepartment->id, 'model_id' => $printerModel->id, 'status' => 'written_off',
            'organization_id' => $this->other->id,
        ]))->assertStatus(422)->assertJsonValidationErrors([
            'ip_address', 'mac_address', 'organization_id', 'model_id', 'status',
        ]);

        $id = $this->postJson('/api/v1/assets', $this->payload(['model_id' => $printerModel->id, 'category_id' => $this->assetCategory('printer')->id]))
            ->assertCreated()->assertJsonPath('data.manufacturer.name', 'HP')->json('data.id');
        $this->postJson('/api/v1/assets', $this->payload(['location_id' => $foreignLocation->id, 'department_id' => $foreignDepartment->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['location_id', 'department_id']);

        $this->patchJson("/api/v1/assets/{$id}", ['status' => 'written_off'])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_manual_status_changes_are_limited(): void
    {
        $asset = $this->assetIn($this->org, $this->it);
        $this->actingAs($this->it);

        $this->postJson("/api/v1/assets/{$asset->id}/status", ['status' => 'reserve', 'note' => 'Zaxira'])
            ->assertOk()->assertJsonPath('data.status', 'reserve');
        foreach (['in_use', 'in_repair', 'written_off'] as $status) {
            $this->postJson("/api/v1/assets/{$asset->id}/status", ['status' => $status])->assertStatus(422);
        }
        $asset->forceFill(['status' => 'written_off'])->save();
        $this->postJson("/api/v1/assets/{$asset->id}/status", ['status' => 'in_stock'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->patchJson("/api/v1/assets/{$asset->id}", ['name' => 'X'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_updates_are_recorded_in_history(): void
    {
        $asset = $this->assetIn($this->org, $this->it);
        $this->actingAs($this->it);

        $this->patchJson("/api/v1/assets/{$asset->id}", ['hostname' => 'urg-pc-215', 'room' => '215'])->assertOk();

        $events = $this->getJson("/api/v1/assets/{$asset->id}/events")->assertOk()->json('data');
        $this->assertSame('updated', $events[0]['action']);
        $this->assertEquals(['hostname' => 'urg-pc-215', 'room' => '215'], $events[0]['new_values']); // JSON column: key order not kept
        $this->assertSame('created', end($events)['action']);
    }

    public function test_scope_list_filters_search_and_lookup(): void
    {
        $mine = $this->assetIn($this->org, $this->it, ['inventory_number' => 'A-1', 'hostname' => 'urg-srv-01', 'category_id' => $this->assetCategory('server')->id, 'warranty_until' => now()->addDays(10)->toDateString()]);
        $this->assetIn($this->org, $this->it, ['inventory_number' => 'A-2', 'ip_address' => '10.0.0.5']);
        $head = $this->makeUser(Roles::CENTRAL_IT_HEAD);
        $foreign = $this->assetIn($this->other, $head, ['inventory_number' => 'B-1']);
        $this->actingAs($this->it);

        $ids = fn (string $q) => collect($this->getJson('/api/v1/assets?'.$q)->assertOk()->json('data'))->pluck('inventory_number')->sort()->values()->all();
        $this->assertSame(['A-1', 'A-2'], $ids(''));
        $this->assertSame(['A-1'], $ids('filter[category]='.$this->assetCategory('server')->id));
        $this->assertSame(['A-1'], $ids('search=srv-01'));
        $this->assertSame(['A-2'], $ids('search=10.0.0.5'));
        $this->assertSame(['A-1'], $ids('filter[warranty]=expiring'));
        $this->assertSame(['A-1', 'A-2'], $ids('filter[status]=in_stock'));
        $this->assertSame(['A-1'], $ids('filter[id]='.$mine->id.','.$foreign->id));

        $this->getJson('/api/v1/assets/lookup/A-1')->assertOk()->assertJsonPath('data.id', $mine->id);
        $this->getJson('/api/v1/assets/lookup/B-1')->assertNotFound();
        $this->getJson('/api/v1/assets/lookup/NOPE')->assertNotFound();
        $this->getJson("/api/v1/assets/{$foreign->id}")->assertNotFound();

        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));
        $this->getJson('/api/v1/assets')->assertForbidden();
        $this->getJson('/api/v1/assets/lookup/A-1')->assertForbidden();
    }

    public function test_attachments_follow_asset_scope(): void
    {
        Storage::fake('local');
        $asset = $this->assetIn($this->org, $this->it);
        $this->actingAs($this->it);

        $uuid = $this->post("/api/v1/assets/{$asset->id}/attachments", ['files' => [UploadedFile::fake()->createWithContent('akt.pdf', SafeUploadTest::pdf())]], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.0.file.uuid');
        $this->get("/api/v1/files/{$uuid}")->assertOk();
        $this->assertCount(1, $this->getJson("/api/v1/assets/{$asset->id}/attachments")->json('data'));

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->other));
        $this->get("/api/v1/files/{$uuid}")->assertNotFound();
    }

    public function test_relations_between_assets_in_scope(): void
    {
        $pc = $this->assetIn($this->org, $this->it);
        $switch = $this->assetIn($this->org, $this->it, ['category_id' => $this->assetCategory('network')->id, 'name' => 'Cisco 2960']);
        $foreign = $this->assetIn($this->other, $this->makeUser(Roles::SUPER_ADMIN));
        $this->actingAs($this->it);

        $this->postJson("/api/v1/assets/{$pc->id}/relations", ['related_asset_id' => $switch->id, 'type' => 'connected_to', 'note' => 'Gi0/14'])->assertCreated();
        $this->postJson("/api/v1/assets/{$pc->id}/relations", ['related_asset_id' => $foreign->id, 'type' => 'connected_to'])->assertStatus(422);
        $this->postJson("/api/v1/assets/{$pc->id}/relations", ['related_asset_id' => $pc->id, 'type' => 'part_of'])->assertStatus(422);
        $this->postJson("/api/v1/assets/{$pc->id}/relations", ['related_asset_id' => $switch->id, 'type' => 'connected_to'])->assertStatus(409);

        $this->getJson("/api/v1/assets/{$switch->id}/relations")->assertOk()
            ->assertJsonPath('data.0.asset.id', $pc->id)->assertJsonPath('data.0.direction', 'incoming')->assertJsonPath('data.0.note', 'Gi0/14');
    }

    public function test_delete_only_assets_without_operational_history(): void
    {
        $fresh = $this->assetIn($this->org, $this->it);
        $used = $this->assetIn($this->org, $this->it);
        AssetAssignment::create(['asset_id' => $used->id, 'organization_id' => $this->org->id, 'assigned_at' => now()]);
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org));

        $this->deleteJson("/api/v1/assets/{$used->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/assets/{$fresh->id}")->assertOk();
        $this->assertNull(Asset::find($fresh->id));

        $this->actingAs($this->it);
        $this->deleteJson("/api/v1/assets/{$used->id}")->assertForbidden();
    }
}
