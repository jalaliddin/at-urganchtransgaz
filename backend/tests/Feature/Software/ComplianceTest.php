<?php

namespace Tests\Feature\Software;

use App\Enums\AntivirusEffective;
use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\Organization;
use App\Models\SoftwarePolicy;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesSoftware;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ComplianceTest extends TestCase
{
    use CreatesAssets, CreatesSoftware, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true);
    }

    private function report(Asset $asset, array $attributes): AntivirusStatus
    {
        return AntivirusStatus::create(['asset_id' => $asset->id, 'state' => 'installed', 'reported_at' => now(), 'source' => 'manual', ...$attributes]);
    }

    public function test_effective_antivirus_status_follows_report_and_definition_dates(): void
    {
        $pc = $this->assetIn($this->org, $this->head);
        $this->assertSame(AntivirusEffective::Unknown, AntivirusStatus::effective(null));

        $status = $this->report($pc, ['definitions_updated_at' => now()->subDays(2)]);
        $this->assertSame(AntivirusEffective::Updated, AntivirusStatus::effective($status));
        $status->update(['definitions_updated_at' => now()->subDays(10)]);
        $this->assertSame(AntivirusEffective::Outdated, AntivirusStatus::effective($status));
        app(SettingsService::class)->update(['software.av_outdated_days' => 15]);
        $this->assertSame(AntivirusEffective::Updated, AntivirusStatus::effective($status));
        $status->update(['definitions_updated_at' => null]);
        $this->assertSame(AntivirusEffective::Installed, AntivirusStatus::effective($status));
        $status->update(['state' => 'not_installed']);
        $this->assertSame(AntivirusEffective::NotInstalled, AntivirusStatus::effective($status));
        $status->update(['reported_at' => now()->subDays(40)]);
        $this->assertSame(AntivirusEffective::Unknown, AntivirusStatus::effective($status));
    }

    public function test_antivirus_is_reported_manually_for_devices_in_scope(): void
    {
        $kav = $this->software(['name' => 'Kaspersky Endpoint Security', 'category' => 'antivirus', 'is_antivirus' => true]);
        $pc = $this->assetIn($this->org, $this->head);
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $this->putJson("/api/v1/assets/{$pc->id}/antivirus", ['software_id' => $kav->id, 'state' => 'installed',
            'definitions_updated_at' => now()->subDay()->toIso8601String()])->assertOk()
            ->assertJsonPath('data.effective', 'updated')->assertJsonPath('data.product', 'Kaspersky Endpoint Security');
        $office = $this->software();
        $this->putJson("/api/v1/assets/{$pc->id}/antivirus", ['software_id' => $office->id, 'state' => 'installed'])
            ->assertStatus(422)->assertJsonValidationErrors('software_id');
        $this->putJson("/api/v1/assets/{$pc->id}/antivirus", ['state' => 'not_installed'])->assertOk()->assertJsonPath('data.effective', 'not_installed');

        $foreign = $this->assetIn(Organization::factory()->create(), $this->head);
        $this->putJson("/api/v1/assets/{$foreign->id}/antivirus", ['state' => 'installed'])->assertNotFound();
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org))->putJson("/api/v1/assets/{$pc->id}/antivirus", ['state' => 'installed'])->assertForbidden();
    }

    public function test_compliance_counts_devices_antivirus_and_missing_required_software_per_organization(): void
    {
        $office = $this->software(['name' => 'Microsoft Office']);
        $branch = Organization::factory()->create(['parent_id' => $this->org->id]);
        $other = Organization::factory()->create();
        $pc1 = $this->assetIn($branch, $this->head, ['name' => 'PC-1']);
        $pc2 = $this->assetIn($branch, $this->head, ['name' => 'PC-2']);
        $this->assetIn($branch, $this->head, ['name' => 'Printer', 'category_id' => $this->assetCategory('printer')->id]);
        $this->assetIn($branch, $this->head, ['name' => 'Old PC', 'status' => 'lost']);
        $this->assetIn($other, $this->head, ['name' => 'Outside']);
        $this->report($pc1, ['definitions_updated_at' => now()]);

        $this->actingAs($this->head);
        $this->postJson('/api/v1/software-policies', ['software_id' => $office->id, 'organization_id' => $this->org->id])->assertCreated();
        $this->postJson('/api/v1/software-policies', ['software_id' => $this->software(['name' => 'Only for other'])->id, 'organization_id' => $other->id])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->postJson("/api/v1/assets/{$pc1->id}/software", ['software_id' => $office->id])->assertCreated();

        $rows = collect($this->getJson('/api/v1/software-compliance')->assertOk()->json('data.organizations'));
        $row = $rows->firstWhere('organization.id', $branch->id);
        $this->assertSame(2, $row['devices']);
        $this->assertSame(1, $row['missing_required']);
        $this->assertSame(['updated' => 1, 'installed' => 0, 'outdated' => 0, 'not_installed' => 0, 'unknown' => 1], $row['antivirus']);
        $this->assertNull($rows->firstWhere('organization.id', $other->id));

        $this->getJson('/api/v1/software-compliance/devices?filter[problem]=missing_required')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pc2->id)->assertJsonPath('data.0.missing', ['Microsoft Office']);
        $this->getJson('/api/v1/software-compliance/devices?filter[problem]=unprotected')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.antivirus', 'unknown');
    }

    public function test_policies_are_scoped_and_only_unrestricted_users_set_global_ones(): void
    {
        $other = Organization::factory()->create();
        $global = SoftwarePolicy::create(['software_id' => $this->software(['name' => 'Kaspersky'])->id]);
        $foreign = SoftwarePolicy::create(['software_id' => $this->software(['name' => 'Foreign'])->id, 'organization_id' => $other->id]);
        $own = SoftwarePolicy::create(['software_id' => $this->software(['name' => 'Own'])->id, 'organization_id' => $this->org->id]);
        $office = $this->software(['name' => 'Office']);

        $this->actingAs($this->head);
        $this->assertEqualsCanonicalizing([$global->id, $own->id], collect($this->getJson('/api/v1/software-policies')->assertOk()->json('data'))->pluck('id')->all());
        $this->putJson("/api/v1/software-policies/{$foreign->id}", ['software_id' => $office->id, 'organization_id' => $this->org->id])->assertNotFound();
        $this->deleteJson("/api/v1/software-policies/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/software-policies/{$global->id}", ['software_id' => $office->id, 'organization_id' => $this->org->id])->assertForbidden();
        $this->deleteJson("/api/v1/software-policies/{$global->id}")->assertForbidden();
        $this->postJson('/api/v1/software-policies', ['software_id' => $office->id])->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->putJson("/api/v1/software-policies/{$own->id}", ['software_id' => $office->id, 'organization_id' => null])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->putJson("/api/v1/software-policies/{$own->id}", ['software_id' => $office->id, 'organization_id' => $this->org->id])->assertOk();

        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));
        $this->postJson('/api/v1/software-policies', ['software_id' => $office->id])->assertCreated();
        $this->assertCount(4, $this->getJson('/api/v1/software-policies')->assertOk()->json('data'));
    }
}
