<?php

namespace Tests\Feature\Itsm;

use App\Models\Asset;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Database\Seeders\AssetsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

/** Final-review findings of Phase 7.3, each reproduced before its fix. */
class ItsmReviewFixesTest extends TestCase
{
    use CreatesAssets, CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $staff;

    private User $it;

    private Asset $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->seed(AssetsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Tashkent'));
        $this->center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->head = $this->makeStaff(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->staff = $this->makeStaff(Roles::CENTRAL_IT_STAFF, $this->center, true);
        $this->it = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->router = $this->assetIn($this->branch, $this->head, ['name' => 'Router']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function change(User $as, array $attributes = []): int
    {
        return $this->actingAs($as)->postJson('/api/v1/changes', [
            'organization_id' => $this->branch->id, 'title' => 'Router', 'type' => 'normal', 'category' => 'network', 'risk' => 'low',
            'plan' => 'Reja', 'planned_start' => '2026-11-21 22:00', 'planned_end' => '2026-11-21 23:00', 'asset_ids' => [$this->router->id],
            ...$attributes,
        ])->assertCreated()->json('data.id');
    }

    public function test_an_article_shows_its_source_ticket_only_to_readers_who_can_open_that_ticket(): void
    {
        $ticket = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->branch), ['title' => 'Direktor noutbuki: VPN tokeni']);
        $id = $this->actingAs($this->it)->postJson('/api/v1/kb-articles', ['title' => 'VPN', 'type' => 'solution', 'audience' => 'all',
            'body' => 'Matn', 'source_ticket_id' => $ticket->id, 'publish' => true])->assertCreated()
            ->assertJsonPath('data.source_ticket.number', $ticket->number)->json('data.id');

        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->branch))->getJson("/api/v1/kb-articles/{$id}")->assertOk()
            ->assertJsonPath('data.source_ticket', null);
    }

    public function test_a_problem_shows_its_known_error_article_only_to_readers_of_that_article(): void
    {
        $article = $this->actingAs($this->it)->postJson('/api/v1/kb-articles', ['title' => 'Ichki yechim', 'type' => 'solution', 'audience' => 'it',
            'body' => 'Matn', 'publish' => true])->json('data.id');
        $problem = $this->postJson('/api/v1/problems', ['organization_id' => $this->branch->id, 'title' => 'Muammo', 'priority' => 'low'])->json('data.id');
        $this->putJson("/api/v1/problems/{$problem}", ['title' => 'Muammo', 'priority' => 'low', 'kb_article_id' => $article])->assertOk()
            ->assertJsonPath('data.article.id', $article);

        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->branch))->getJson("/api/v1/problems/{$problem}")->assertOk()
            ->assertJsonPath('data.article', null);
    }

    public function test_whoever_submitted_a_change_cannot_approve_it(): void
    {
        $id = $this->change($this->staff);
        $this->actingAs($this->head)->postJson("/api/v1/changes/{$id}/submit")->assertOk();

        $this->getJson("/api/v1/changes/{$id}")->assertJsonPath('data.can.decide', false);
        $this->postJson("/api/v1/changes/{$id}/approve")->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_a_revised_change_drops_the_earlier_decision(): void
    {
        $id = $this->change($this->staff);
        $this->postJson("/api/v1/changes/{$id}/submit")->assertOk();
        $this->actingAs($this->head)->postJson("/api/v1/changes/{$id}/reject", ['comment' => 'Kechroq'])->assertOk();
        $this->actingAs($this->staff)->postJson("/api/v1/changes/{$id}/revise")->assertOk()
            ->assertJsonPath('data.decided_by', null)->assertJsonPath('data.decided_at', null)->assertJsonPath('data.decision_comment', null);
    }

    public function test_an_implementer_without_change_permissions_cannot_run_the_change(): void
    {
        $id = $this->change($this->staff, ['implementer_id' => $this->it->id]);
        $this->postJson("/api/v1/changes/{$id}/submit")->assertOk();
        $this->actingAs($this->head)->postJson("/api/v1/changes/{$id}/approve")->assertOk();
        $this->it->syncRoles([Roles::EMPLOYEE]);

        $this->actingAs($this->it->fresh())->postJson("/api/v1/changes/{$id}/start")->assertForbidden();
    }

    public function test_an_unchanged_responsible_person_who_lost_access_does_not_block_editing(): void
    {
        $problem = $this->actingAs($this->it)->postJson('/api/v1/problems', ['organization_id' => $this->branch->id, 'title' => 'Muammo',
            'priority' => 'low', 'assignee_id' => $this->staff->id])->json('data.id');
        $change = $this->change($this->it, ['implementer_id' => $this->staff->id]);
        $plan = $this->postJson('/api/v1/maintenance-plans', ['organization_id' => $this->branch->id, 'title' => 'UPS', 'interval_count' => 1,
            'interval_unit' => 'month', 'start_on' => '2026-11-25', 'asset_ids' => [$this->router->id], 'assignee_id' => $this->staff->id])->json('data.id');
        $this->staff->forceFill(['is_active' => false])->save();
        Role::findOrCreate('nobody', 'web');

        $this->putJson("/api/v1/problems/{$problem}", ['title' => 'Muammo (aniqlandi)', 'priority' => 'low', 'assignee_id' => $this->staff->id])->assertOk();
        $this->putJson("/api/v1/changes/{$change}", ['title' => 'Router (tungi)', 'implementer_id' => $this->staff->id])->assertOk();
        $this->putJson("/api/v1/maintenance-plans/{$plan}", ['title' => 'UPS xizmati', 'assignee_id' => $this->staff->id])->assertOk();
        $this->putJson("/api/v1/problems/{$problem}", ['title' => 'X', 'priority' => 'low', 'assignee_id' => $this->makeUser(Roles::ORG_HEAD, $this->branch)->id])
            ->assertStatus(422)->assertJsonValidationErrors('assignee_id');
    }
}
