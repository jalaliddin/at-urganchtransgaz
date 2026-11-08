<?php

namespace Tests\Feature\Warehouse;

use App\Models\MaterialCategory;
use App\Models\Organization;
use App\Models\Unit;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class WarehouseCatalogTest extends TestCase
{
    use CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarehouse();
        $this->org = Organization::factory()->create();
    }

    public function test_catalog_is_read_by_warehouse_and_requisition_users_and_edited_by_managers(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $this->assertCount(7, $this->getJson('/api/v1/units')->assertOk()->json('data'));
        $this->assertCount(6, $this->getJson('/api/v1/material-categories')->assertOk()->json('data'));
        $this->postJson('/api/v1/units', ['code' => 'roll', 'name' => 'rulon'])->assertForbidden();

        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));
        $this->getJson('/api/v1/units')->assertForbidden();
        $this->getJson('/api/v1/materials')->assertForbidden();

        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true));
        $unit = $this->postJson('/api/v1/units', ['code' => 'roll', 'name' => 'rulon', 'name_ru' => 'рулон'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/units', ['code' => 'roll', 'name' => 'x'])->assertStatus(422)->assertJsonValidationErrors('code');
        $category = $this->postJson('/api/v1/material-categories', ['name' => 'UPS batareyalari'])->assertCreated()->json('data.id');
        $this->putJson("/api/v1/material-categories/{$category}", ['name' => 'UPS akkumulyatorlari'])
            ->assertOk()->assertJsonPath('data.name', 'UPS akkumulyatorlari');

        $material = $this->postJson('/api/v1/materials', [
            'name' => 'Termoqog\'oz', 'category_id' => $category, 'unit_id' => $unit, 'min_quantity' => '5.5',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'MT-00001')
            ->assertJsonPath('data.min_quantity', '5.500')
            ->assertJsonPath('data.unit.name', 'rulon')
            ->json('data.id');

        $this->deleteJson("/api/v1/units/{$unit}")->assertStatus(409);
        $this->deleteJson("/api/v1/material-categories/{$category}")->assertStatus(409);
        $this->deleteJson("/api/v1/materials/{$material}")->assertOk();
        $this->deleteJson("/api/v1/units/{$unit}")->assertOk();
    }

    public function test_material_codes_are_unique_and_expiry_needs_batches(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true));
        $base = ['name' => 'HP 85A', 'category_id' => MaterialCategory::where('code', 'cartridge')->value('id'), 'unit_id' => Unit::where('code', 'pcs')->value('id')];

        $this->postJson('/api/v1/materials', [...$base, 'code' => 'CE285A'])->assertCreated()->assertJsonPath('data.code', 'CE285A');
        $this->postJson('/api/v1/materials', [...$base, 'code' => 'CE285A'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/materials', [...$base, 'track_expiry' => true, 'track_batches' => false])
            ->assertStatus(422)->assertJsonValidationErrors('track_batches');
        $this->postJson('/api/v1/materials', [...$base, 'min_quantity' => -1])->assertStatus(422)->assertJsonValidationErrors('min_quantity');
    }

    public function test_tracking_flags_are_locked_once_the_material_has_stock(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true));
        [$warehouse, $material] = $this->stockFixture();
        DB::table('stock_balances')->insert(['warehouse_id' => $warehouse->id, 'material_id' => $material->id, 'batch' => '', 'quantity' => 3, 'unit_cost' => 0]);

        $this->putJson("/api/v1/materials/{$material->id}", ['track_batches' => true])->assertStatus(422)->assertJsonValidationErrors('track_batches');
        $this->putJson("/api/v1/materials/{$material->id}", ['name' => 'HP 85A (asl)', 'min_quantity' => 2])->assertOk()
            ->assertJsonPath('data.name', 'HP 85A (asl)');
        $this->deleteJson("/api/v1/materials/{$material->id}")->assertStatus(409);
    }

    public function test_materials_list_searches_and_filters(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $this->material(['name' => 'HP 85A kartrij', 'code' => 'CE285A']);
        $this->material(['name' => 'A4 qog\'oz', 'code' => 'A4', 'category_id' => MaterialCategory::where('code', 'paper')->value('id')]);
        $this->material(['name' => 'Eski kartrij', 'is_active' => false]);

        $this->getJson('/api/v1/materials?search=ce285')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'CE285A');
        $this->getJson('/api/v1/materials?filter[category]='.MaterialCategory::where('code', 'paper')->value('id'))->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/materials?filter[active]=1')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/materials')->assertJsonPath('meta.total', 3);
    }

    public function test_warehouses_are_scoped_to_accessible_organizations(): void
    {
        $other = Organization::factory()->create();
        $foreign = $this->warehouseIn($other, ['name' => 'Begona ombor']);
        $head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org);
        $this->actingAs($head);

        $id = $this->postJson('/api/v1/warehouses', ['organization_id' => $this->org->id, 'name' => 'Markaziy ombor', 'responsible_user_id' => $head->id])
            ->assertCreated()->assertJsonPath('data.organization.id', $this->org->id)->assertJsonPath('data.responsible.id', $head->id)->json('data.id');
        $this->postJson('/api/v1/warehouses', ['organization_id' => $this->org->id, 'name' => 'Markaziy ombor'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/warehouses', ['organization_id' => $other->id, 'name' => 'X'])->assertStatus(422)->assertJsonValidationErrors('organization_id');

        $this->assertSame([$id], array_column($this->getJson('/api/v1/warehouses')->assertOk()->json('data'), 'id'));
        $this->putJson("/api/v1/warehouses/{$foreign->id}", ['name' => 'Y'])->assertNotFound();
        $this->putJson("/api/v1/warehouses/{$id}", ['name' => 'Markaziy ombor №1'])->assertOk()->assertJsonPath('data.name', 'Markaziy ombor №1');

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $this->getJson('/api/v1/warehouses')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/warehouses', ['organization_id' => $this->org->id, 'name' => 'Z'])->assertForbidden();
    }

    public function test_warehouse_with_stock_cannot_be_deleted(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org));
        $warehouse = $this->warehouseIn($this->org);
        $empty = $this->warehouseIn($this->org);
        DB::table('stock_balances')->insert(['warehouse_id' => $warehouse->id, 'material_id' => $this->material()->id, 'batch' => '', 'quantity' => 0, 'unit_cost' => 0]);

        $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/warehouses/{$empty->id}")->assertOk();
    }
}
