<?php

namespace Tests\Feature\Itsm;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\MaintenanceTask;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Itsm\MaintenanceNotification;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $it;

    private Asset $ups;

    private Asset $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00', 'Asia/Tashkent'));
        $this->center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->ups = $this->assetIn($this->branch, $this->head, ['name' => 'APC Smart-UPS 3000', 'category_id' => $this->assetCategory('power')->id]);
        $this->server = $this->assetIn($this->branch, $this->head, ['name' => 'Dell R740', 'category_id' => $this->assetCategory('server')->id]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function plan(array $attributes = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->it)->postJson('/api/v1/maintenance-plans', [
            'organization_id' => $this->branch->id, 'title' => 'UPS va server profilaktikasi', 'interval_count' => 1, 'interval_unit' => 'month',
            'start_on' => '2026-11-25', 'asset_ids' => [$this->ups->id, $this->server->id], 'assignee_id' => $this->it->id,
            'checklist' => ['Akkumulyatorni tekshirish', 'Changni tozalash'], 'lead_days' => 7, ...$attributes,
        ]);
    }

    private function taskOf(Asset $asset, string $status = 'planned'): MaintenanceTask
    {
        return MaintenanceTask::query()->withoutGlobalScopes()->where('asset_id', $asset->id)->where('status', $status)->latest('id')->firstOrFail();
    }

    public function test_a_plan_opens_one_item_per_device_and_finishing_one_schedules_the_next(): void
    {
        $plan = $this->plan()->assertCreated()->assertJsonPath('data.assets_count', 2)->assertJsonPath('data.next_due', '2026-11-25')->json('data.id');
        $this->assertSame(2, MaintenanceTask::query()->withoutGlobalScopes()->where('plan_id', $plan)->count());
        $ups = $this->taskOf($this->ups);
        $done = fn (array $payload) => $this->actingAs($this->it)->postJson("/api/v1/maintenance-tasks/{$ups->id}/complete", $payload);

        $done(['result' => 'issues', 'checklist' => [['done' => true]]])->assertStatus(422)->assertJsonValidationErrors('checklist');
        $done(['result' => 'issues', 'completed_on' => '2026-11-21', 'checklist' => [['done' => true], ['done' => true]]])
            ->assertStatus(422)->assertJsonValidationErrors('completed_on');
        $done(['result' => 'issues', 'note' => 'Akkumulyator 60%', 'checklist' => [['done' => true, 'note' => '60%'], ['done' => false]]])
            ->assertOk()->assertJsonPath('data.status', 'done')->assertJsonPath('data.result', 'issues')
            ->assertJsonPath('data.checklist.0.step', 'Akkumulyatorni tekshirish')->assertJsonPath('data.checklist.0.note', '60%');
        $done(['result' => 'ok', 'checklist' => [['done' => true], ['done' => true]]])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->assertSame('2026-12-25', $this->taskOf($this->ups)->due_on->toDateString());
        $event = AssetEvent::query()->where('asset_id', $this->ups->id)->where('action', 'preventive_done')->sole();
        $this->assertSame('issues', $event->new_values['result'] ?? null);

        $server = $this->taskOf($this->server);
        $this->actingAs($this->it)->postJson("/api/v1/maintenance-tasks/{$server->id}/skip")->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/maintenance-tasks/{$server->id}/skip", ['reason' => 'Server ish rejimida'])->assertOk()->assertJsonPath('data.status', 'skipped');
        $this->assertSame('2026-12-25', $this->taskOf($this->server)->due_on->toDateString());

        $this->getJson('/api/v1/maintenance-tasks?filter[status]=planned')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/maintenance-tasks?filter[asset]={$this->ups->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/maintenance-tasks?filter[mine]=1&filter[status]=planned')->assertOk()->assertJsonCount(2, 'data');

        $orgHead = $this->makeUser(Roles::ORG_HEAD, $this->branch);
        $next = $this->taskOf($this->ups);
        $this->actingAs($orgHead)->getJson('/api/v1/maintenance-tasks')->assertOk()->assertJsonPath('data.0.can.perform', false);
        $this->postJson("/api/v1/maintenance-tasks/{$next->id}/skip", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()))
            ->postJson("/api/v1/maintenance-tasks/{$next->id}/skip", ['reason' => 'x'])->assertNotFound();
    }

    public function test_plan_rules_keep_devices_and_people_inside_the_organization(): void
    {
        $central = $this->assetIn($this->center, $this->head);
        $this->plan(['asset_ids' => [$central->id]])->assertStatus(422)->assertJsonValidationErrors('asset_ids');
        $this->plan(['assignee_id' => $this->makeUser(Roles::ORG_HEAD, $this->branch)->id])->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->plan(['interval_count' => 0, 'interval_unit' => 'year'])->assertStatus(422)->assertJsonValidationErrors(['interval_count', 'interval_unit']);
        $this->plan([], $this->makeUser(Roles::ORG_HEAD, $this->branch))->assertForbidden();

        $id = $this->plan()->json('data.id');
        $this->actingAs($this->it)->putJson("/api/v1/maintenance-plans/{$id}", ['asset_ids' => [$this->ups->id]])->assertOk()->assertJsonPath('data.assets_count', 1);
        $this->assertSame('cancelled', $this->taskOf($this->server, 'cancelled')->status->value);
        $this->putJson("/api/v1/maintenance-plans/{$id}", ['is_active' => false])->assertOk();
        $this->assertSame(0, MaintenanceTask::query()->withoutGlobalScopes()->where('plan_id', $id)->where('status', 'planned')->count());

        $this->putJson("/api/v1/maintenance-plans/{$id}", ['is_active' => true])->assertOk()->assertJsonPath('data.next_due', '2026-11-25');
        $ups = $this->taskOf($this->ups);
        $this->postJson("/api/v1/maintenance-tasks/{$ups->id}/complete", ['result' => 'ok', 'checklist' => [['done' => true], ['done' => true]]])->assertOk();
        $this->deleteJson("/api/v1/maintenance-plans/{$id}")->assertStatus(409);
        $fresh = $this->plan(['title' => 'Bo\'sh reja'])->json('data.id');
        $this->deleteJson("/api/v1/maintenance-plans/{$fresh}")->assertOk();
        $this->assertDatabaseMissing('maintenance_tasks', ['plan_id' => $fresh]);
    }

    public function test_the_daily_job_creates_nothing_twice_and_reminds_once(): void
    {
        Notification::fake();
        $id = $this->plan()->json('data.id');

        $this->artisan('maintenance:daily')->assertSuccessful();
        $this->artisan('maintenance:daily')->assertSuccessful();
        $this->assertSame(2, MaintenanceTask::query()->withoutGlobalScopes()->where('plan_id', $id)->count());
        Notification::assertSentToTimes($this->it, MaintenanceNotification::class, 2);
        Notification::assertSentTo($this->it, MaintenanceNotification::class, fn ($n) => $n->event === 'due_soon');

        $this->travelTo(CarbonImmutable::parse('2026-11-26 08:00', 'Asia/Tashkent'));
        $this->ups->forceFill(['status' => AssetStatus::WrittenOff])->save();
        $this->artisan('maintenance:daily')->assertSuccessful();
        $this->artisan('maintenance:daily')->assertSuccessful();
        Notification::assertSentToTimes($this->it, MaintenanceNotification::class, 3);
        Notification::assertSentTo($this->it, MaintenanceNotification::class, fn ($n) => $n->event === 'overdue' && $n->asset === 'Dell R740');
        $this->assertSame('cancelled', $this->taskOf($this->ups, 'cancelled')->status->value);
        $this->actingAs($this->it)->getJson('/api/v1/maintenance-tasks?filter[overdue]=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.overdue', true);
    }
}
