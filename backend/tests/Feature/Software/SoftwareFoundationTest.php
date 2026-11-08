<?php

namespace Tests\Feature\Software;

use App\Models\Organization;
use App\Models\Role;
use App\Models\SoftwareInstallation;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesSoftware;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class SoftwareFoundationTest extends TestCase
{
    use CreatesAssets, CreatesSoftware, InteractsWithAccess, RefreshDatabase;

    /** @return array<string, array{string, list<string>}> */
    public static function defaults(): array
    {
        return [
            'central head' => ['central-it-head', ['view', 'install', 'manage', 'agents', 'export']],
            'central staff' => ['central-it-staff', ['view', 'install', 'agents', 'export']],
            'org it' => ['org-it-responsible', ['view', 'install', 'export']],
            'org head' => ['org-head', ['view', 'export']],
            'auditor' => ['auditor', ['view', 'export']],
            'accountant' => ['accountant', ['view']],
            'employee' => ['employee', []],
        ];
    }

    /** @param  list<string>  $expected */
    #[DataProvider('defaults')]
    public function test_default_roles_get_software_permissions(string $role, array $expected): void
    {
        $this->seed(RbacSeeder::class);
        $granted = Role::findByName($role, 'web')->permissions->pluck('name')
            ->filter(fn (string $p) => str_starts_with($p, 'software.'))->map(fn (string $p) => substr($p, 9))->sort()->values()->all();
        sort($expected);
        $this->assertSame($expected, $granted);
    }

    public function test_catalog_is_read_by_software_users_and_edited_by_managers(): void
    {
        $this->seedAssets();
        $org = Organization::factory()->create();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $org));
        $this->getJson('/api/v1/software')->assertOk();
        $this->postJson('/api/v1/software', ['name' => '7-Zip', 'category' => 'utility', 'license_type' => 'open_source'])->assertForbidden();

        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $org));
        $this->getJson('/api/v1/software')->assertForbidden();

        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $org));
        $kav = $this->postJson('/api/v1/software', [
            'name' => 'Kaspersky Endpoint Security', 'vendor' => 'Kaspersky', 'category' => 'antivirus', 'license_type' => 'subscription', 'is_antivirus' => true,
        ])->assertCreated()->assertJsonPath('data.is_antivirus', true)->json('data.id');
        $this->postJson('/api/v1/software', ['name' => '  kaspersky   endpoint security ', 'category' => 'antivirus', 'license_type' => 'subscription'])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/software', ['name' => 'X', 'category' => 'games', 'license_type' => 'subscription'])
            ->assertStatus(422)->assertJsonValidationErrors('category');

        $this->getJson('/api/v1/software?filter[antivirus]=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/software?search=kasper')->assertOk()->assertJsonPath('data.0.id', $kav);
        $this->putJson("/api/v1/software/{$kav}", ['vendor' => 'Kaspersky Lab'])->assertOk()->assertJsonPath('data.vendor', 'Kaspersky Lab');

        $asset = $this->assetIn($org, $this->makeUser(Roles::CENTRAL_IT_HEAD, $org));
        SoftwareInstallation::create(['asset_id' => $asset->id, 'software_id' => $kav, 'name' => 'Kaspersky Endpoint Security',
            'normalized_name' => 'kaspersky endpoint security', 'source' => 'manual']);
        $this->deleteJson("/api/v1/software/{$kav}")->assertStatus(409);
        $free = $this->software(['name' => 'Notepad++']);
        $this->deleteJson("/api/v1/software/{$free->id}")->assertOk();
    }

    public function test_outdated_threshold_setting_defaults_to_seven_days(): void
    {
        $this->assertSame(7, app(SettingsService::class)->get('software.av_outdated_days'));
    }
}
