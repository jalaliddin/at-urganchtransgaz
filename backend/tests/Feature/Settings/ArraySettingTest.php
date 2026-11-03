<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ArraySettingTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));
    }

    public function test_array_setting_has_default_and_persists(): void
    {
        $settings = app(SettingsService::class);
        $this->assertSame([1, 2, 3, 4, 5], $settings->get('helpdesk.work_days'));
        $this->assertSame([], $settings->get('helpdesk.holidays'));

        $this->putJson('/api/v1/settings', ['values' => [
            'helpdesk.work_days' => [1, 2, 3, 4, 5, 6],
            'helpdesk.holidays' => ['2026-01-01', '2026-03-21'],
        ]])->assertOk();

        $this->assertSame([1, 2, 3, 4, 5, 6], $settings->get('helpdesk.work_days'));
        $this->assertSame(['2026-01-01', '2026-03-21'], $settings->get('helpdesk.holidays'));
    }

    public function test_array_items_are_validated(): void
    {
        $this->putJson('/api/v1/settings', ['values' => ['helpdesk.work_days' => [0, 8]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values.helpdesk.work_days.0', 'values.helpdesk.work_days.1']);

        $this->putJson('/api/v1/settings', ['values' => ['helpdesk.holidays' => ['31.12.2026']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values.helpdesk.holidays.0']);

        $this->putJson('/api/v1/settings', ['values' => ['helpdesk.work_start' => '9am']])
            ->assertStatus(422);
    }

    public function test_other_helpdesk_and_file_defaults(): void
    {
        $settings = app(SettingsService::class);
        $this->assertSame('09:00', $settings->get('helpdesk.work_start'));
        $this->assertSame('18:00', $settings->get('helpdesk.work_end'));
        $this->assertSame('13:00', $settings->get('helpdesk.lunch_start'));
        $this->assertSame('14:00', $settings->get('helpdesk.lunch_end'));
        $this->assertSame(3, $settings->get('helpdesk.auto_close_days'));
        $this->assertSame(7, $settings->get('helpdesk.reopen_days'));
        $this->assertSame(60, $settings->get('helpdesk.due_soon_minutes'));
        $this->assertSame(10, $settings->get('files.max_upload_mb'));
        $this->assertTrue($settings->get('notifications.mail_enabled'));
    }
}
