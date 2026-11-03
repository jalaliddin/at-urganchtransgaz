<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TicketPriority;
use App\Models\Organization;
use App\Models\TicketCategory;
use App\Models\TicketSlaPolicy;
use App\Services\Helpdesk\SlaResolver;
use App\Support\Permissions\Roles;
use Database\Seeders\HelpdeskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class CategoryAndSlaApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seed(HelpdeskSeeder::class);
        $this->org = Organization::factory()->create();
    }

    public function test_employee_lists_active_categories_but_cannot_manage(): void
    {
        TicketCategory::where('code', 'server')->update(['is_active' => false]);
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));

        $codes = collect($this->getJson('/api/v1/ticket-categories?filter[active]=1')->assertOk()->json('data'))->pluck('code');
        $this->assertCount(8, $codes);
        $this->assertNotContains('server', $codes);

        $this->postJson('/api/v1/ticket-categories', ['name' => 'Yangi'])->assertForbidden();
    }

    public function test_user_without_helpdesk_permissions_cannot_list(): void
    {
        $role = \App\Models\Role::findByName(Roles::EMPLOYEE, 'web');
        $role->revokePermissionTo('helpdesk.create');
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));

        $this->getJson('/api/v1/ticket-categories')->assertForbidden();
    }

    public function test_manager_creates_updates_and_deletes_category(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true));

        $id = $this->postJson('/api/v1/ticket-categories', [
            'name' => '1C dasturi', 'name_ru' => 'Программа 1С', 'sort_order' => 95,
        ])->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');

        $this->putJson("/api/v1/ticket-categories/{$id}", ['name' => '1C', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.name', '1C')->assertJsonPath('data.is_active', false);

        $this->postJson('/api/v1/ticket-categories', ['name' => ''])->assertStatus(422);
        $this->deleteJson("/api/v1/ticket-categories/{$id}")->assertOk();
        $this->assertDatabaseMissing('ticket_categories', ['id' => $id]);
    }

    public function test_sla_policy_crud_and_uniqueness(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true));
        $printer = TicketCategory::where('code', 'printer')->firstOrFail();

        $id = $this->postJson('/api/v1/ticket-sla-policies', [
            'priority' => 'high', 'category_id' => $printer->id, 'response_minutes' => 30, 'resolution_minutes' => 240,
        ])->assertCreated()->assertJsonPath('data.category.id', $printer->id)->json('data.id');

        $this->postJson('/api/v1/ticket-sla-policies', [
            'priority' => 'high', 'category_id' => $printer->id, 'response_minutes' => 10, 'resolution_minutes' => 20,
        ])->assertStatus(422)->assertJsonValidationErrors('priority');

        $this->postJson('/api/v1/ticket-sla-policies', [
            'priority' => 'high', 'category_id' => null, 'response_minutes' => 10, 'resolution_minutes' => 20,
        ])->assertStatus(422)->assertJsonValidationErrors('priority');

        $this->postJson('/api/v1/ticket-sla-policies', [
            'priority' => 'low', 'category_id' => $printer->id, 'response_minutes' => 100, 'resolution_minutes' => 50,
        ])->assertStatus(422)->assertJsonValidationErrors('resolution_minutes');

        $this->putJson("/api/v1/ticket-sla-policies/{$id}", ['response_minutes' => 45, 'resolution_minutes' => 300])
            ->assertOk()->assertJsonPath('data.response_minutes', 45);

        $this->assertCount(5, $this->getJson('/api/v1/ticket-sla-policies')->assertOk()->json('data'));
        $this->deleteJson("/api/v1/ticket-sla-policies/{$id}")->assertOk();
    }

    public function test_org_it_responsible_cannot_manage_sla(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $this->getJson('/api/v1/ticket-sla-policies')->assertOk();
        $this->postJson('/api/v1/ticket-sla-policies', [
            'priority' => 'low', 'response_minutes' => 1, 'resolution_minutes' => 2,
        ])->assertForbidden();
    }

    public function test_resolver_precedence(): void
    {
        $resolver = app(SlaResolver::class);
        $printer = TicketCategory::where('code', 'printer')->firstOrFail();

        $this->assertSame(['response' => 60, 'resolution' => 480], $resolver->for(TicketPriority::High, $printer->id));

        TicketSlaPolicy::create(['priority' => 'high', 'category_id' => $printer->id, 'response_minutes' => 15, 'resolution_minutes' => 90]);
        $this->assertSame(['response' => 15, 'resolution' => 90], $resolver->for(TicketPriority::High, $printer->id));
        $this->assertSame(['response' => 60, 'resolution' => 480], $resolver->for(TicketPriority::High, null));

        TicketSlaPolicy::whereNull('category_id')->where('priority', 'low')->delete();
        $this->assertSame(['response' => 480, 'resolution' => 2400], $resolver->for(TicketPriority::Low, null));
    }

    public function test_calendar_settings_endpoint(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->org, true));

        $this->getJson('/api/v1/helpdesk/calendar')->assertOk()
            ->assertJsonPath('data.work_days', [1, 2, 3, 4, 5])
            ->assertJsonPath('data.work_start', '09:00')
            ->assertJsonPath('data.holidays', []);

        $payload = [
            'work_days' => [1, 2, 3, 4, 5, 6], 'work_start' => '08:30', 'work_end' => '17:30',
            'lunch_start' => '12:30', 'lunch_end' => '13:30', 'holidays' => ['2026-12-31'],
            'auto_close_days' => 5, 'reopen_days' => 10, 'due_soon_minutes' => 30,
        ];
        $this->putJson('/api/v1/helpdesk/calendar', $payload)->assertOk()->assertJsonPath('data.work_start', '08:30')
            ->assertJsonPath('data.holidays', ['2026-12-31']);

        $this->putJson('/api/v1/helpdesk/calendar', [...$payload, 'work_end' => '08:00'])
            ->assertStatus(422)->assertJsonValidationErrors('work_end');
        $this->putJson('/api/v1/helpdesk/calendar', [...$payload, 'lunch_start' => '07:00'])
            ->assertStatus(422)->assertJsonValidationErrors('lunch_start');
        $this->putJson('/api/v1/helpdesk/calendar', [...$payload, 'lunch_end' => '12:00'])
            ->assertStatus(422)->assertJsonValidationErrors('lunch_end');
    }

    public function test_calendar_update_requires_manage(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $this->getJson('/api/v1/helpdesk/calendar')->assertOk();
        $this->putJson('/api/v1/helpdesk/calendar', ['work_days' => [1]])->assertForbidden();
    }
}
