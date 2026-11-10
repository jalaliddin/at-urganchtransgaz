<?php

namespace Tests\Feature\Itsm;

use App\Models\Organization;
use App\Models\User;
use App\Services\Exports\ChangeReport;
use App\Services\Exports\MaintenanceTaskReport;
use App\Services\Exports\ProblemReport;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Database\Seeders\AssetsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ItsmReportsTest extends TestCase
{
    use CreatesAssets, CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $branch;

    private Organization $other;

    private User $head;

    private User $it;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->seed(AssetsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00', 'Asia/Tashkent'));
        $center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['name' => 'Xiva GTS', 'parent_id' => $center->id]);
        $this->other = Organization::factory()->create(['name' => 'Begona']);
        $this->head = $this->makeStaff(Roles::CENTRAL_IT_HEAD, $center, true);
        $this->grantAccess($this->head, $this->other);
        $this->it = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->branch);
    }

    private function seedWork(Organization $organization, string $title): void
    {
        $this->actingAs($this->head)->postJson('/api/v1/problems', ['organization_id' => $organization->id, 'title' => "{$title} muammo", 'priority' => 'high',
            'assignee_id' => $organization->is($this->branch) ? $this->it->id : null])->assertCreated();
        $change = $this->postJson('/api/v1/changes', ['organization_id' => $organization->id, 'title' => "{$title} o'zgarish", 'type' => 'normal',
            'category' => 'server', 'risk' => 'low', 'plan' => 'Reja', 'planned_start' => '2026-11-23 20:00', 'planned_end' => '2026-11-23 21:00'])->json('data.id');
        $this->postJson("/api/v1/changes/{$change}/submit")->assertOk();
        $asset = $this->assetIn($organization, $this->head, ['name' => "{$title} UPS"]);
        $this->postJson('/api/v1/maintenance-plans', ['organization_id' => $organization->id, 'title' => "{$title} UPS xizmati", 'interval_count' => 1,
            'interval_unit' => 'month', 'start_on' => '2026-11-24', 'asset_ids' => [$asset->id]])->assertCreated();
    }

    public function test_dashboard_blocks_count_work_in_scope(): void
    {
        $this->seedWork($this->branch, 'Xiva');
        $this->seedWork($this->other, 'Begona');

        $this->actingAs($this->it)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.problems.open', 1)->assertJsonPath('data.problems.mine', 1)
            ->assertJsonPath('data.changes.pending_approval', 1)->assertJsonPath('data.changes.this_week', 0)
            ->assertJsonPath('data.maintenance.due_week', 1)->assertJsonPath('data.maintenance.overdue', 0);
        $this->actingAs($this->head)->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.problems.open', 2)
            ->assertJsonPath('data.changes.pending_approval', 2);
        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->branch))->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonMissingPath('data.problems')->assertJsonMissingPath('data.changes')->assertJsonMissingPath('data.maintenance');
    }

    public function test_exports_apply_scope(): void
    {
        $this->seedWork($this->branch, 'Xiva');
        $this->seedWork($this->other, 'Begona');
        Auth::forgetGuards();

        $problems = iterator_to_array(app(ProblemReport::class)->rows($this->it, [], 'uz'), false);
        $this->assertSame(['Xiva muammo'], array_column($problems, 1));
        $changes = iterator_to_array(app(ChangeReport::class)->rows($this->it, ['status' => 'pending_approval'], 'uz'), false);
        $this->assertSame(["Xiva o'zgarish"], array_column($changes, 1));
        $tasks = iterator_to_array(app(MaintenanceTaskReport::class)->rows($this->it, [], 'uz'), false);
        $this->assertSame(['Xiva UPS xizmati'], array_column($tasks, 0));
        $this->assertCount(2, iterator_to_array(app(ProblemReport::class)->rows($this->head, [], 'uz'), false));
    }
}
