<?php

namespace Tests\Feature\Software;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Software;
use App\Models\SoftwareInstallation;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesSoftware;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class InstallationsLicensesTest extends TestCase
{
    use CreatesAssets, CreatesSoftware, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $head;

    private User $it;

    private Asset $pc;

    private Software $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->pc = $this->assetIn($this->org, $this->head, ['name' => 'PC-214']);
        $this->office = $this->software(['name' => 'Microsoft Office 2021']);
    }

    private function license(int $seats, array $extra = []): int
    {
        return $this->actingAs($this->head)->postJson('/api/v1/software-licenses', [
            'software_id' => $this->office->id, 'organization_id' => $this->org->id, 'number' => 'L-2026-01',
            'license_key' => 'ABCDE-FGHIJ-KLMNO-PQRST-12345', 'seats' => $seats, 'expires_at' => now()->addYear()->toDateString(), ...$extra,
        ])->assertCreated()->json('data.id');
    }

    public function test_devices_get_catalog_and_unrecognised_software(): void
    {
        $this->actingAs($this->it);
        $this->postJson("/api/v1/assets/{$this->pc->id}/software", ['software_id' => $this->office->id, 'version' => '16.0'])
            ->assertCreated()->assertJsonPath('data.software.name', 'Microsoft Office 2021')->assertJsonPath('data.source', 'manual');
        $this->postJson("/api/v1/assets/{$this->pc->id}/software", ['name' => '  microsoft office 2021 '])
            ->assertStatus(422)->assertJsonValidationErrors('software_id');
        $raw = $this->postJson("/api/v1/assets/{$this->pc->id}/software", ['name' => 'AnyDesk', 'version' => '8.0'])
            ->assertCreated()->assertJsonPath('data.software', null)->json('data.id');

        $this->getJson("/api/v1/assets/{$this->pc->id}/software")->assertOk()->assertJsonCount(2, 'data.installations')
            ->assertJsonPath('data.antivirus.effective', 'unknown');

        $this->actingAs($this->head);
        $this->getJson('/api/v1/software-unrecognized')->assertOk()->assertJsonPath('data.0.name', 'AnyDesk')->assertJsonPath('data.0.devices', 1);
        $anydesk = $this->postJson('/api/v1/software', ['name' => 'AnyDesk', 'category' => 'utility', 'license_type' => 'freeware'])->json('data.id');
        $this->postJson("/api/v1/software/{$anydesk}/adopt", ['name' => 'anydesk'])->assertOk()->assertJsonPath('data.linked', 1);
        $this->assertSame($anydesk, SoftwareInstallation::query()->find($raw)?->software_id);

        $foreign = $this->assetIn(Organization::factory()->create(), $this->head);
        $this->actingAs($this->it)->getJson("/api/v1/assets/{$foreign->id}/software")->assertNotFound();
        $this->deleteJson("/api/v1/software-installations/{$raw}")->assertOk();
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org))->postJson("/api/v1/assets/{$this->pc->id}/software", ['name' => 'X'])->assertForbidden();
    }

    public function test_license_seats_are_never_over_assigned(): void
    {
        $license = $this->license(1);
        $second = $this->assetIn($this->org, $this->head);
        $this->actingAs($this->head);
        $a = $this->postJson("/api/v1/assets/{$this->pc->id}/software", ['software_id' => $this->office->id, 'license_id' => $license])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/assets/{$second->id}/software", ['software_id' => $this->office->id, 'license_id' => $license])
            ->assertStatus(422)->assertJsonValidationErrors('license_id');
        $b = $this->postJson("/api/v1/assets/{$second->id}/software", ['software_id' => $this->office->id])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/software-licenses')->assertOk()->assertJsonPath('data.0.used', 1)->assertJsonPath('data.0.free', 0);
        $this->putJson("/api/v1/software-licenses/{$license}", ['seats' => 0])->assertStatus(422)->assertJsonValidationErrors('seats');
        $this->putJson("/api/v1/software-licenses/{$license}", ['seats' => 2])->assertOk();
        $this->patchJson("/api/v1/software-installations/{$b}", ['license_id' => $license])->assertOk()->assertJsonPath('data.license.id', $license);

        $other = $this->software(['name' => 'Windows 11 Pro']);
        $win = $this->postJson("/api/v1/assets/{$this->pc->id}/software", ['software_id' => $other->id])->json('data.id');
        $this->patchJson("/api/v1/software-installations/{$win}", ['license_id' => $license])->assertStatus(422)->assertJsonValidationErrors('license_id');
        $this->deleteJson("/api/v1/software-licenses/{$license}")->assertStatus(409);
        $this->assertNotNull($a);
    }

    public function test_license_key_is_encrypted_masked_and_revealed_with_an_audit_entry(): void
    {
        $license = $this->license(5);

        $this->assertStringNotContainsString('ABCDE', (string) DB::table('software_licenses')->where('id', $license)->value('license_key'));
        $list = $this->actingAs($this->it)->getJson('/api/v1/software-licenses')->assertOk()->json('data.0');
        $this->assertSame('••••••••2345', $list['license_key_masked']);
        $this->assertArrayNotHasKey('license_key', $list);
        $this->postJson("/api/v1/software-licenses/{$license}/reveal")->assertForbidden();

        $this->actingAs($this->head)->postJson("/api/v1/software-licenses/{$license}/reveal")->assertOk()
            ->assertJsonPath('data.license_key', 'ABCDE-FGHIJ-KLMNO-PQRST-12345');
        $this->assertTrue(AuditLog::query()->where('event', 'secret_revealed')->where('auditable_id', $license)->exists());
        $this->assertFalse(AuditLog::query()->where('auditable_type', 'software_license')->get()
            ->contains(fn (AuditLog $l) => str_contains(json_encode($l->new_values) ?: '', 'ABCDE')));

        $foreignOrg = Organization::factory()->create();
        $this->postJson('/api/v1/software-licenses', ['software_id' => $this->office->id, 'organization_id' => $foreignOrg->id, 'seats' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $foreignOrg))->getJson("/api/v1/software-licenses/{$license}")->assertNotFound();
        $this->assertSame(1, SoftwareLicense::query()->withoutGlobalScopes()->count());
    }
}
