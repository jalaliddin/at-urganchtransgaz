<?php

namespace Tests\Feature\Software;

use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\Organization;
use App\Models\SoftwareInstallation;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesSoftware;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use CreatesAssets, CreatesSoftware, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $it;

    private Asset $pc;

    private Asset $foreign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
        $head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->pc = $this->assetIn($this->org, $head, ['inventory_number' => 'PC-001']);
        $this->foreign = $this->assetIn(Organization::factory()->create(), $head, ['inventory_number' => 'PC-999']);
    }

    private function upload(string $type, string $csv): TestResponse
    {
        return $this->actingAs($this->it)->post('/api/v1/software-imports', [
            'type' => $type, 'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ], ['Accept' => 'application/json']);
    }

    public function test_installations_are_imported_only_for_devices_in_scope_with_per_line_errors(): void
    {
        $office = $this->software(['name' => 'Microsoft Office 2021']);
        $csv = "inventory_number;name;version;vendor;installed_at\n"
            ."PC-001;Microsoft Office 2021;16.0;Microsoft;2024-02-01\n"
            ."PC-001;AnyDesk;8.0;;\n"
            ."PC-999;Telegram;5.0;;\n"
            ."NOPE-1;Chrome;120;;\n"
            ."PC-001;;1.0;;\n";

        $this->upload('installations', $csv)->assertOk()
            ->assertJsonPath('data.total', 5)->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.errors.0.line', 4)->assertJsonPath('data.errors.1.line', 5)->assertJsonPath('data.errors.2.line', 6);

        $rows = SoftwareInstallation::query()->where('asset_id', $this->pc->id)->get()->keyBy('normalized_name');
        $this->assertSame($office->id, $rows['microsoft office 2021']->software_id);
        $this->assertSame('csv', $rows['anydesk']->source->value);
        $this->assertSame(0, SoftwareInstallation::query()->where('asset_id', $this->foreign->id)->count());
        $this->assertNotNull($this->pc->fresh()?->software_inventoried_at);
    }

    public function test_antivirus_status_is_imported_with_validation(): void
    {
        $this->software(['name' => 'ESET Endpoint Security', 'category' => 'antivirus', 'is_antivirus' => true]);
        $csv = "inventory_number,product,state,definitions_updated_at,last_scan_at\n"
            .'PC-001,ESET Endpoint Security,installed,'.now()->subDay()->format('Y-m-d H:i').",\n"
            ."PC-001,ESET,maybe,,\n";

        $this->upload('antivirus', $csv)->assertOk()->assertJsonPath('data.imported', 1)->assertJsonPath('data.errors.0.line', 3);
        $status = AntivirusStatus::query()->where('asset_id', $this->pc->id)->firstOrFail();
        $this->assertSame('csv', $status->source->value);
        $this->assertNotNull($status->software_id);
    }

    public function test_permissions_header_and_size_limits(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org))->post('/api/v1/software-imports', [
            'type' => 'installations', 'file' => UploadedFile::fake()->createWithContent('a.csv', "inventory_number,name\nPC-001,X\n"),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->upload('installations', "inv,title\nPC-001,X\n")->assertStatus(422)->assertJsonValidationErrors('file');
        config(['software.import_max_rows' => 2]);
        $this->upload('installations', "inventory_number,name\nPC-001,A\nPC-001,B\nPC-001,C\n")->assertStatus(422)->assertJsonValidationErrors('file');
    }
}
