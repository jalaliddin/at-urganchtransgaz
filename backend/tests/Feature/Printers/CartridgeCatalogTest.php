<?php

namespace Tests\Feature\Printers;

use App\Models\AssetModel;
use App\Models\CartridgeReplacement;
use App\Models\Material;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPrinters;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class CartridgeCatalogTest extends TestCase
{
    use CreatesPrinters, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private AssetModel $m404;

    private AssetModel $m428;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPrinters();
        $this->center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->m404 = $this->printerModel('LaserJet Pro M404');
        $this->m428 = $this->printerModel('LaserJet Pro M428');
    }

    public function test_a_catalog_manager_creates_a_cartridge_with_its_own_material_and_printer_models(): void
    {
        $payload = ['name' => 'HP 59A', 'part_number' => 'CF259A', 'kind' => 'toner', 'color' => 'black', 'page_yield' => 3000,
            'printer_model_ids' => [$this->m404->id, $this->m428->id]];

        $created = $this->actingAs($this->head)->postJson('/api/v1/cartridge-models', $payload)->assertCreated()
            ->assertJsonPath('data.part_number', 'CF259A')->assertJsonPath('data.kind', 'toner')
            ->assertJsonCount(2, 'data.printer_models')->json('data');
        $material = Material::query()->findOrFail($created['material']['id']);
        $this->assertStringStartsWith('MT-', $material->code);
        $this->assertSame('HP 59A (CF259A)', $material->name);
        $this->assertSame('cartridge', $material->category->code);
        $this->assertSame('pcs', $material->unit->code);

        $this->postJson('/api/v1/cartridge-models', $payload)->assertStatus(422)->assertJsonValidationErrors('part_number');
        $this->postJson('/api/v1/cartridge-models', [...$payload, 'part_number' => 'CF259X', 'material_id' => $material->id])
            ->assertStatus(422)->assertJsonValidationErrors('material_id');
        $computer = AssetModel::create(['manufacturer_id' => $this->m404->manufacturer_id, 'category_id' => $this->assetCategory('computer')->id, 'name' => 'ProDesk 400']);
        $this->postJson('/api/v1/cartridge-models', [...$payload, 'part_number' => 'CF259X', 'printer_model_ids' => [$computer->id]])
            ->assertStatus(422)->assertJsonValidationErrors('printer_model_ids.0');

        $free = $this->material(['name' => 'Canon 725']);
        $this->postJson('/api/v1/cartridge-models', ['name' => 'Canon 725', 'kind' => 'toner', 'color' => 'black', 'material_id' => $free->id])
            ->assertCreated()->assertJsonPath('data.material.id', $free->id)->assertJsonPath('data.part_number', null)
            ->assertJsonCount(0, 'data.printer_models');

        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_STAFF, $this->center, true))
            ->postJson('/api/v1/cartridge-models', [...$payload, 'part_number' => 'Q2612A'])->assertForbidden();
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->center))->getJson('/api/v1/cartridge-models')->assertForbidden();
    }

    public function test_the_list_filters_and_counts_stock_only_in_visible_warehouses(): void
    {
        $fits = $this->cartridge(['name' => 'HP 59A', 'part_number' => 'CF259A'], [$this->m404]);
        $this->cartridge(['name' => 'HP 12A', 'part_number' => 'Q2612A', 'is_active' => false]);
        $this->stockUp($this->warehouseIn($this->branch), $fits, 5, $this->head);
        $this->stockUp($this->warehouseIn(Organization::factory()->create()), $fits, 7, $this->head);
        $it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);

        $this->actingAs($it)->getJson('/api/v1/cartridge-models')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/cartridge-models?filter[printer_model]='.$this->m404->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $fits->id)->assertJsonPath('data.0.stock', '5.000');
        $this->getJson('/api/v1/cartridge-models?filter[active]=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/cartridge-models?search=Q2612')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'HP 12A');
        $this->getJson('/api/v1/cartridge-models?filter[kind]=ink')->assertOk()->assertJsonCount(0, 'data');

        $models = $this->getJson('/api/v1/printer-models')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$this->m404->id, $this->m428->id], array_column($models, 'id'));
    }

    public function test_used_cartridges_keep_their_material_and_cannot_be_deleted(): void
    {
        $used = $this->cartridge(['part_number' => 'CF259A'], [$this->m404]);
        $unused = $this->cartridge(['name' => 'HP 12A', 'part_number' => 'Q2612A'], [$this->m428]);
        $printer = $this->printerIn($this->branch, $this->head);
        CartridgeReplacement::query()->create(['organization_id' => $this->branch->id, 'asset_id' => $printer->id,
            'cartridge_model_id' => $used->id, 'replaced_at' => '2026-11-01', 'source' => 'other', 'performed_by' => $this->head->id]);

        $this->actingAs($this->head)->putJson("/api/v1/cartridge-models/{$used->id}", ['name' => 'HP 59A original', 'kind' => 'toner',
            'color' => 'black', 'printer_model_ids' => [$this->m428->id]])->assertOk()
            ->assertJsonPath('data.name', 'HP 59A original')->assertJsonPath('data.printer_models.0.id', $this->m428->id)
            ->assertJsonPath('data.can_delete', false);
        $this->putJson("/api/v1/cartridge-models/{$used->id}", ['name' => 'HP 59A', 'kind' => 'toner', 'color' => 'black',
            'material_id' => $this->material()->id])->assertStatus(422)->assertJsonValidationErrors('material_id');
        $this->deleteJson("/api/v1/cartridge-models/{$used->id}")->assertStatus(409);

        $this->deleteJson("/api/v1/cartridge-models/{$unused->id}")->assertOk();
        $this->assertDatabaseMissing('printer_cartridge_compatibilities', ['cartridge_model_id' => $unused->id]);
        $this->assertDatabaseHas('materials', ['id' => $unused->material_id]);
    }
}
