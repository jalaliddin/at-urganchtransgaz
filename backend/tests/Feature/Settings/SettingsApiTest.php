<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
    }

    public function test_auditor_can_view_settings_with_defaults(): void
    {
        $this->actingAs($this->makeUser(Roles::AUDITOR, $this->root, true));

        $response = $this->getJson('/api/v1/settings')->assertOk();

        $entry = collect($response->json('data'))->firstWhere('key', 'security.password_min_length');
        $this->assertSame(['key' => 'security.password_min_length', 'type' => 'integer', 'value' => 10, 'default' => 10, 'public' => false], $entry);
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/settings')->assertUnauthorized();
    }

    public function test_employee_gets_403(): void
    {
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->root));

        $this->getJson('/api/v1/settings')->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    public function test_super_admin_updates_and_change_is_audited(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->putJson('/api/v1/settings', ['values' => ['security.password_min_length' => 12]])->assertOk();

        $this->assertSame(12, app(SettingsService::class)->get('security.password_min_length'));
        $log = AuditLog::where('event', 'settings_updated')->latest('id')->first();
        $this->assertSame(['security.password_min_length' => 10], $log->old_values);
        $this->assertSame(['security.password_min_length' => 12], $log->new_values);
    }

    public function test_unchanged_values_are_not_audited(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->putJson('/api/v1/settings', ['values' => ['security.password_min_length' => 10]])->assertOk();

        $this->assertSame(0, AuditLog::where('event', 'settings_updated')->count());
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->putJson('/api/v1/settings', ['values' => ['foo' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values.foo']);
    }

    public function test_invalid_value_is_rejected(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->putJson('/api/v1/settings', ['values' => ['security.password_min_length' => 3]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values.security.password_min_length']);
    }

    public function test_required_setting_cannot_be_blanked(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->putJson('/api/v1/settings', ['values' => ['general.system_name' => '']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values.general.system_name']);
        $this->putJson('/api/v1/settings', ['values' => ['general.support_email' => null]])->assertOk();
    }

    public function test_central_it_head_cannot_update(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true));

        $this->putJson('/api/v1/settings', ['values' => ['general.system_name' => 'X']])->assertForbidden();
    }

    public function test_public_endpoint_exposes_only_public_values(): void
    {
        app(SettingsService::class)->update(['general.support_phone' => '+998 62 224-00-00'], null);

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertExactJson(['data' => [
                'system_name' => 'AT.urtg.uz',
                'organization_name' => '"Urganchtransgaz" MCHJ',
                'support_phone' => '+998 62 224-00-00',
                'support_email' => null,
            ]]);
    }
}
