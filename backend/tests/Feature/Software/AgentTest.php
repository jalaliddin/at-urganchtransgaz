<?php

namespace Tests\Feature\Software;

use App\Models\AgentCheckin;
use App\Models\AgentToken;
use App\Models\AntivirusStatus;
use App\Models\Asset;
use App\Models\Organization;
use App\Models\SoftwareInstallation;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesSoftware;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use CreatesAssets, CreatesSoftware, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $staff;

    private Asset $pc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
        $this->staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->org);
        $this->pc = $this->assetIn($this->org, $this->staff, ['name' => 'PC-214']);
    }

    private function register(?Asset $asset = null): string
    {
        return $this->actingAs($this->staff)->postJson('/api/v1/assets/'.($asset ?? $this->pc)->id.'/agent', ['name' => 'Inventory agent'])
            ->assertCreated()->json('data.token');
    }

    /** @param  array<string, mixed>  $payload */
    private function checkin(string $token, array $payload): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/agent/checkin', $payload);
    }

    public function test_token_is_shown_once_and_stored_only_as_a_hash(): void
    {
        $token = $this->register();

        $this->assertStringStartsWith('atg_', $token);
        $this->assertSame(hash('sha256', $token), AgentToken::query()->value('token_hash'));
        $list = $this->getJson('/api/v1/device-agents')->assertOk()->getContent();
        $this->assertStringNotContainsString($token, (string) $list);
        $this->assertStringNotContainsString(hash('sha256', $token), (string) $list);
        $this->postJson("/api/v1/assets/{$this->pc->id}/agent", ['name' => 'Again'])->assertStatus(409);

        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org))->postJson("/api/v1/assets/{$this->pc->id}/agent", ['name' => 'x'])->assertForbidden();
        $foreign = $this->assetIn(Organization::factory()->create(), $this->staff);
        $this->actingAs($this->staff)->postJson("/api/v1/assets/{$foreign->id}/agent", ['name' => 'x'])->assertNotFound();
    }

    public function test_checkin_replaces_agent_rows_keeps_manual_rows_and_reports_antivirus(): void
    {
        $office = $this->software(['name' => 'Microsoft Office 2021']);
        $kav = $this->software(['name' => 'Kaspersky Endpoint Security', 'category' => 'antivirus', 'is_antivirus' => true]);
        $this->actingAs($this->staff)->postJson("/api/v1/assets/{$this->pc->id}/software", ['name' => '1C Buxgalteriya', 'version' => '8.3'])->assertCreated();
        $token = $this->register();

        $this->checkin($token, [
            'platform' => 'Windows 11 Pro', 'agent_version' => '1.0.0',
            'software' => [
                ['name' => 'microsoft office 2021', 'version' => '16.0.1'],
                ['name' => 'AnyDesk', 'version' => '8.0', 'vendor' => 'AnyDesk Software'],
                ['name' => '1C Buxgalteriya', 'version' => '8.3.24'],
            ],
            'antivirus' => ['product' => 'Kaspersky Endpoint Security', 'state' => 'installed', 'definitions_updated_at' => now()->subHours(3)->toIso8601String()],
        ])->assertOk()->assertJsonPath('data.software', 3);

        $rows = SoftwareInstallation::query()->where('asset_id', $this->pc->id)->get()->keyBy('normalized_name');
        $this->assertSame($office->id, $rows['microsoft office 2021']->software_id);
        $this->assertSame('agent', $rows['anydesk']->source->value);
        $this->assertSame('manual', $rows['1c buxgalteriya']->source->value);
        $this->assertSame('8.3.24', $rows['1c buxgalteriya']->version);
        $this->assertSame($kav->id, AntivirusStatus::query()->value('software_id'));
        $this->assertNotNull($this->pc->fresh()?->software_inventoried_at);

        $this->checkin($token, ['software' => [['name' => 'Microsoft Office 2021', 'version' => '16.0.2']]])->assertOk();
        $names = SoftwareInstallation::query()->where('asset_id', $this->pc->id)->orderBy('normalized_name')->pluck('normalized_name')->all();
        $this->assertSame(['1c buxgalteriya', 'microsoft office 2021'], $names);
        $this->assertSame(2, AgentCheckin::query()->where('status', 'accepted')->count());
        $this->actingAs($this->staff)->getJson("/api/v1/assets/{$this->pc->id}/software")->assertJsonPath('data.agent.platform', 'Windows 11 Pro')
            ->assertJsonPath('data.antivirus.effective', 'updated');
    }

    public function test_a_token_writes_only_its_own_device_and_dies_when_revoked_or_rotated(): void
    {
        $other = $this->assetIn($this->org, $this->staff, ['name' => 'PC-215']);
        $this->actingAs($this->staff)->postJson("/api/v1/assets/{$other->id}/software", ['name' => 'Chrome'])->assertCreated();
        $token = $this->register();

        $this->checkin($token, ['software' => [['name' => 'Chrome']]])->assertOk();
        $this->assertSame(1, SoftwareInstallation::query()->where('asset_id', $other->id)->count());
        $this->assertSame('manual', SoftwareInstallation::query()->where('asset_id', $other->id)->firstOrFail()->source->value);

        $this->checkin('atg_wrong', ['software' => []])->assertUnauthorized();
        $agent = $this->actingAs($this->staff)->getJson('/api/v1/device-agents')->json('data.0.id');
        $fresh = $this->postJson("/api/v1/device-agents/{$agent}/tokens")->assertCreated()->json('data.token');
        $this->checkin($token, ['software' => []])->assertUnauthorized();
        $this->checkin($fresh, ['software' => []])->assertOk();

        $this->actingAs($this->staff)->postJson("/api/v1/device-agents/{$agent}/revoke")->assertOk()->assertJsonPath('data.is_active', false);
        $this->checkin($fresh, ['software' => []])->assertUnauthorized();
    }

    public function test_invalid_payloads_are_rejected_and_logged_and_checkins_are_throttled(): void
    {
        $token = $this->register();

        $this->checkin($token, ['software' => [['version' => '1.0']]])->assertStatus(422);
        $this->assertSame('rejected', AgentCheckin::query()->value('status'));

        for ($i = 0; $i < 29; $i++) {
            $this->checkin($token, ['software' => []])->assertOk();
        }
        $this->checkin($token, ['software' => []])->assertStatus(429);
    }

    public function test_antivirus_dates_in_the_future_are_rejected(): void
    {
        $token = $this->register();
        $av = fn ($date) => ['software' => [['name' => 'X']], 'antivirus' => ['state' => 'installed', 'definitions_updated_at' => $date]];

        $this->checkin($token, $av(now()->addDays(3)->toIso8601String()))->assertStatus(422)->assertJsonValidationErrors('antivirus.definitions_updated_at');
        $this->checkin($token, ['software' => [['name' => 'X']], 'antivirus' => ['state' => 'installed', 'last_scan_at' => now()->addDays(3)->toIso8601String()]])
            ->assertStatus(422)->assertJsonValidationErrors('antivirus.last_scan_at');
        // A client clock a few minutes ahead is tolerated.
        $this->checkin($token, $av(now()->addMinutes(10)->toIso8601String()))->assertOk();
    }
}
