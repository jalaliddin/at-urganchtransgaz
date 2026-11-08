<?php

namespace Tests\Feature\Software;

use App\Models\AntivirusStatus;
use App\Models\Organization;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Notifications\Software\LicenseExpiringNotification;
use App\Services\Exports\SoftwareComplianceReport;
use App\Services\Exports\SoftwareInstallationReport;
use App\Services\Exports\SoftwareLicenseReport;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesSoftware;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class SoftwareReportsTest extends TestCase
{
    use CreatesAssets, CreatesSoftware, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org);
    }

    private function license(int $days, ?Organization $org = null): SoftwareLicense
    {
        return SoftwareLicense::query()->withoutGlobalScopes()->create([
            'software_id' => $this->software()->id, 'organization_id' => ($org ?? $this->org)->id, 'number' => 'L-'.$days,
            'license_key' => 'SECRET-KEY-'.$days, 'seats' => 10, 'expires_at' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function test_licence_expiry_is_announced_once(): void
    {
        Notification::fake();
        $this->license(20);
        $this->license(200);

        $this->artisan('software:license-check')->assertSuccessful();
        $this->artisan('software:license-check')->assertSuccessful();

        Notification::assertSentToTimes($this->head, LicenseExpiringNotification::class, 1);
    }

    public function test_dashboard_shows_the_software_widget_in_scope(): void
    {
        $pc1 = $this->assetIn($this->org, $this->head);
        $this->assetIn($this->org, $this->head);
        $this->assetIn(Organization::factory()->create(), $this->head);
        AntivirusStatus::create(['asset_id' => $pc1->id, 'state' => 'installed', 'definitions_updated_at' => now(), 'reported_at' => now(), 'source' => 'manual']);
        $this->license(10);

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.software.devices', 2)
            ->assertJsonPath('data.software.protected', 1)
            ->assertJsonPath('data.software.unprotected', 1)
            ->assertJsonPath('data.software.licenses_expiring', 1);
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonMissingPath('data.software');
    }

    public function test_exports_apply_scope_and_never_contain_licence_keys(): void
    {
        $this->assetIn($this->org, $this->head, ['inventory_number' => 'PC-1']);
        $this->assetIn(Organization::factory()->create(), $this->head, ['inventory_number' => 'PC-X']);
        $this->license(10);
        $this->license(10, Organization::factory()->create());
        $it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
        Auth::logout();

        $devices = iterator_to_array(app(SoftwareComplianceReport::class)->rows($it, [], 'uz'), false);
        $this->assertSame(['PC-1'], array_column($devices, 0));
        $licenses = iterator_to_array(app(SoftwareLicenseReport::class)->rows($it, [], 'uz'), false);
        $this->assertCount(1, $licenses);
        $this->assertStringNotContainsString('SECRET-KEY', json_encode($licenses) ?: '');
        $this->assertSame([], iterator_to_array(app(SoftwareInstallationReport::class)->rows($it, [], 'uz'), false));
    }
}
