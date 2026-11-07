<?php

namespace Tests\Feature\Assets;

use App\Models\Organization;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AssetCatalogTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
    }

    public function test_reference_data_is_read_by_asset_users_and_edited_by_catalog_managers(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $this->assertCount(10, $this->getJson('/api/v1/asset-categories')->assertOk()->json('data'));
        $this->postJson('/api/v1/manufacturers', ['name' => 'HP'])->assertForbidden();

        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true));
        $hp = $this->postJson('/api/v1/manufacturers', ['name' => 'HP', 'website' => 'https://hp.com'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/manufacturers', ['name' => 'HP'])->assertStatus(422)->assertJsonValidationErrors('name');
        $model = $this->postJson('/api/v1/asset-models', [
            'manufacturer_id' => $hp, 'category_id' => $this->assetCategory()->id, 'name' => 'ProDesk 400 G7', 'specs' => 'i5, 8 GB',
        ])->assertCreated()->assertJsonPath('data.manufacturer.name', 'HP')->json('data.id');
        $this->postJson('/api/v1/asset-categories', ['name' => 'Planshet', 'is_networked' => true])->assertCreated();

        $this->getJson('/api/v1/asset-models?filter[manufacturer]='.$hp)->assertOk()->assertJsonPath('data.0.id', $model);
        $this->deleteJson("/api/v1/manufacturers/{$hp}")->assertStatus(409);
        $this->deleteJson('/api/v1/asset-categories/'.$this->assetCategory()->id)->assertStatus(409);
        $this->deleteJson("/api/v1/asset-models/{$model}")->assertOk();
        $this->deleteJson("/api/v1/manufacturers/{$hp}")->assertOk();
    }

    public function test_users_without_asset_permissions_cannot_read_the_catalog(): void
    {
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));

        $this->getJson('/api/v1/asset-categories')->assertForbidden();
        $this->getJson('/api/v1/locations')->assertForbidden();
    }

    public function test_locations_belong_to_an_organization_in_scope(): void
    {
        $other = Organization::factory()->create();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $building = $this->postJson('/api/v1/locations', ['organization_id' => $this->org->id, 'name' => 'Bosh bino'])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/locations', ['organization_id' => $this->org->id, 'name' => '214-xona', 'parent_id' => $building])
            ->assertCreated()->assertJsonPath('data.parent.id', $building);
        $this->postJson('/api/v1/locations', ['organization_id' => $other->id, 'name' => 'Ombor'])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');

        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $other, false));
        $foreign = $this->postJson('/api/v1/locations', ['organization_id' => $other->id, 'name' => 'Ombor'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/locations', ['organization_id' => $other->id, 'name' => 'Xona', 'parent_id' => $building])
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $this->assertCount(2, $this->getJson('/api/v1/locations')->assertOk()->json('data'));
        $this->putJson("/api/v1/locations/{$foreign}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("/api/v1/locations/{$building}")->assertStatus(409);
    }
}
