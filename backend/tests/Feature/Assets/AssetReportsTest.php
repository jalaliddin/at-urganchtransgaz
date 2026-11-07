<?php

namespace Tests\Feature\Assets;

use App\Jobs\GenerateExportJob;
use App\Models\Asset;
use App\Models\Document;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AssetReportsTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $a;

    private Organization $b;

    private User $itA;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedAssets();
        $root = Organization::factory()->create();
        $this->a = Organization::factory()->create(['parent_id' => $root->id, 'name' => 'Urganch GTS']);
        $this->b = Organization::factory()->create(['parent_id' => $root->id]);
        $this->itA = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);
        $admin = $this->makeUser(Roles::SUPER_ADMIN);
        $this->assetIn($this->a, $admin, ['inventory_number' => 'A-1', 'cost' => '1000.00', 'warranty_until' => now()->addDays(5)->toDateString()]);
        $this->assetIn($this->a, $admin, ['inventory_number' => 'A-2', 'cost' => '2500.50', 'category_id' => $this->assetCategory('printer')->id])->forceFill(['status' => 'in_use'])->save();
        $this->assetIn($this->a, $admin, ['inventory_number' => 'A-3', 'cost' => '500.00'])->forceFill(['status' => 'in_repair'])->save();
        $this->assetIn($this->a, $admin, ['inventory_number' => 'A-4', 'cost' => '9999.00'])->forceFill(['status' => 'written_off'])->save();
        $this->assetIn($this->b, $admin, ['inventory_number' => 'B-1', 'cost' => '7000.00']);
    }

    public function test_stats_for_organization_scope(): void
    {
        $this->actingAs($this->itA);

        $data = $this->getJson('/api/v1/assets/stats')->assertOk()->json('data');

        $this->assertSame(4, $data['total']);
        $this->assertSame(3, $data['active']);
        $this->assertSame(['in_stock' => 1, 'in_use' => 1, 'in_repair' => 1, 'reserve' => 0, 'written_off' => 1, 'lost' => 0], $data['by_status']);
        $this->assertSame('4000.50', $data['total_cost']);
        $this->assertSame(1, $data['warranty_expiring']);
        $this->assertSame([['id' => $this->a->id, 'name' => 'Urganch GTS', 'total' => 3, 'in_use' => 1, 'in_repair' => 1]], $data['by_organization']);
        $this->assertSame(2, collect($data['by_category'])->firstWhere('code', 'computer')['total']);

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.assets.active', 3)->assertJsonPath('data.assets.in_repair', 1)->assertJsonPath('data.assets.warranty_expiring', 1);
    }

    public function test_export_runs_in_queue_with_owner_scope_and_filters(): void
    {
        $export = ReportExport::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $this->itA->id, 'type' => 'assets', 'format' => 'xlsx',
            'filters' => ['status' => 'in_stock,in_use'], 'status' => 'pending',
        ]);

        GenerateExportJob::dispatchSync($export->id);

        $export->refresh();
        $this->assertSame('done', $export->status->value);
        $path = tempnam(sys_get_temp_dir(), 'a').'.xlsx';
        file_put_contents($path, (string) Storage::disk('local')->get($export->file->path));
        $reader = new Reader;
        $reader->open($path);
        $numbers = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $numbers[] = $row->toArray()[0];
            }
        }
        $reader->close();
        $this->assertSame(['A-1', 'A-2'], array_slice($numbers, 1));

        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->a));
        $this->postJson('/api/v1/exports', ['type' => 'assets', 'format' => 'xlsx'])->assertForbidden();
    }

    public function test_documents_link_to_visible_assets(): void
    {
        $document = Document::create(['organization_id' => $this->a->id, 'type' => 'invoice', 'title' => 'Hisob-faktura 15']);
        $assetId = Asset::query()->withoutGlobalScopes()->where('inventory_number', 'A-1')->value('id');
        $foreignId = Asset::query()->withoutGlobalScopes()->where('inventory_number', 'B-1')->value('id');
        $this->actingAs($this->itA);

        $this->postJson("/api/v1/documents/{$document->id}/links", ['type' => 'asset', 'id' => $foreignId])->assertNotFound();
        $this->postJson("/api/v1/documents/{$document->id}/links", ['type' => 'asset', 'id' => $assetId])
            ->assertCreated()->assertJsonPath('data.target.number', 'A-1');
        $this->getJson("/api/v1/assets/{$assetId}/documents")->assertOk()->assertJsonPath('data.0.id', $document->id);
    }
}
