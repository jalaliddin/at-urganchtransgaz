<?php

namespace Tests\Feature\Itsm;

use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\ChangeHistory;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Itsm\ChangeNotification;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ChangeTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $head;

    private User $staff;

    private User $it;

    private Asset $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00', 'Asia/Tashkent'));
        $this->center = Organization::factory()->create(['name' => 'Urganchtransgaz']);
        $this->branch = Organization::factory()->create(['name' => 'Xiva GTS', 'parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->center, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->router = $this->assetIn($this->branch, $this->head, ['name' => 'MikroTik CCR', 'category_id' => $this->assetCategory('network')->id]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function draft(array $attributes = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->staff)->postJson('/api/v1/changes', [
            'organization_id' => $this->branch->id, 'title' => 'Router proshivkasini yangilash', 'type' => 'normal', 'category' => 'network',
            'risk' => 'medium', 'plan' => '1. Zaxira nusxa 2. Yangilash 3. Tekshirish', 'backout_plan' => 'Eski proshivkaga qaytish',
            'planned_start' => '2026-11-21 22:00', 'planned_end' => '2026-11-21 23:00', 'asset_ids' => [$this->router->id],
            'implementer_id' => $this->it->id, ...$attributes,
        ]);
    }

    private function act(int $id, string $action, array $payload = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->staff)->postJson("/api/v1/changes/{$id}/{$action}", $payload);
    }

    public function test_a_normal_change_is_approved_by_someone_else_then_implemented_and_recorded_on_its_assets(): void
    {
        $id = $this->draft()->assertCreated()->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.planned_start', '2026-11-21T17:00:00+00:00')->assertJsonPath('data.assets.0.id', $this->router->id)->json('data.id');
        $this->assertMatchesRegularExpression('/^CHG-2026-\d{5}$/', $this->getJson("/api/v1/changes/{$id}")->json('data.number'));

        $this->act($id, 'submit')->assertOk()->assertJsonPath('data.status', 'pending_approval');
        $this->actingAs($this->staff)->putJson("/api/v1/changes/{$id}", ['title' => 'X'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->act($id, 'approve')->assertForbidden();
        $this->act($id, 'start', [], $this->it)->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->act($id, 'approve', ['comment' => 'Kechasi bajaring'], $this->head)->assertOk()->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decided_by.id', $this->head->id);
        $this->act($id, 'start', [], $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch))->assertForbidden();
        $this->act($id, 'start', [], $this->it)->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->act($id, 'complete', ['success' => true], $this->it)->assertStatus(422)->assertJsonValidationErrors('result');
        $this->act($id, 'complete', ['success' => true, 'result' => 'RouterOS 7.16 o\'rnatildi'], $this->it)->assertOk()
            ->assertJsonPath('data.status', 'implemented')->assertJsonPath('data.result', 'RouterOS 7.16 o\'rnatildi');
        $this->act($id, 'cancel', ['reason' => 'kech'])->assertStatus(422);

        $event = AssetEvent::query()->where('asset_id', $this->router->id)->where('action', 'change_implemented')->sole();
        $this->assertSame('implemented', $event->new_values['status'] ?? null);
        $this->assertSame(['created', 'submitted', 'approved', 'started', 'implemented'],
            ChangeHistory::query()->where('change_id', $id)->orderBy('id')->pluck('event')->map(fn ($e) => $e->value)->all());

        $own = $this->draft([], $this->head)->json('data.id');
        $this->act($own, 'submit', [], $this->head)->assertOk();
        $this->act($own, 'approve', [], $this->head)->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_a_rejected_change_goes_back_to_draft_and_plans_are_required_at_submit(): void
    {
        $id = $this->draft(['planned_start' => null, 'backout_plan' => null])->assertCreated()->json('data.id');
        $this->act($id, 'submit')->assertStatus(422)->assertJsonValidationErrors(['planned_start', 'backout_plan']);
        $this->actingAs($this->staff)->putJson("/api/v1/changes/{$id}", ['planned_start' => '2026-11-21 23:30', 'backout_plan' => 'Qaytarish'])
            ->assertOk();
        $this->act($id, 'submit')->assertStatus(422)->assertJsonValidationErrors('planned_end');
        $this->actingAs($this->staff)->putJson("/api/v1/changes/{$id}", ['planned_start' => '2026-11-21 21:00'])->assertOk();
        $this->act($id, 'submit')->assertOk();

        $this->act($id, 'reject', [], $this->head)->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->act($id, 'reject', ['comment' => 'Ish vaqtida emas'], $this->head)->assertOk()->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.decision_comment', 'Ish vaqtida emas');
        $this->act($id, 'revise', [], $this->it)->assertForbidden();
        $this->act($id, 'revise')->assertOk()->assertJsonPath('data.status', 'draft');
        $this->actingAs($this->staff)->putJson("/api/v1/changes/{$id}", ['title' => 'Router proshivkasi (tungi)'])->assertOk();
        $this->actingAs($this->it)->putJson("/api/v1/changes/{$id}", ['title' => 'Boshqa'])->assertForbidden();
        $this->act($id, 'cancel', [], $this->staff)->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->act($id, 'cancel', ['reason' => 'Kerak emas'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_standard_and_emergency_changes_skip_approval_and_approvers_hear_about_the_rest(): void
    {
        Notification::fake();
        $standard = $this->draft(['type' => 'standard', 'risk' => 'low', 'backout_plan' => null])->json('data.id');
        $this->act($standard, 'submit')->assertOk()->assertJsonPath('data.status', 'approved');
        Notification::assertNotSentTo($this->head, ChangeNotification::class);

        $emergency = $this->draft(['type' => 'emergency', 'risk' => 'high'])->json('data.id');
        $this->act($emergency, 'submit')->assertOk()->assertJsonPath('data.status', 'approved');
        Notification::assertSentTo($this->head, ChangeNotification::class, fn ($n) => $n->event === 'emergency');

        $normal = $this->draft()->json('data.id');
        $this->act($normal, 'submit')->assertOk();
        Notification::assertSentTo($this->head, ChangeNotification::class, fn ($n) => $n->event === 'approval');
        Notification::assertSentTo($this->it, ChangeNotification::class, fn ($n) => $n->event === 'assigned');
        $this->act($normal, 'approve', [], $this->head)->assertOk();
        Notification::assertSentTo($this->staff, ChangeNotification::class, fn ($n) => $n->event === 'approved');
    }

    public function test_assets_stay_inside_the_subtree_and_overlapping_work_on_a_device_is_flagged(): void
    {
        $central = $this->assetIn($this->center, $this->head, ['name' => 'Core switch']);
        $foreign = $this->assetIn(Organization::factory()->create(), $this->head);
        $this->draft(['asset_ids' => [$central->id]])->assertStatus(422)->assertJsonValidationErrors('asset_ids');
        $this->draft(['asset_ids' => [$foreign->id]])->assertStatus(422)->assertJsonValidationErrors('asset_ids');
        $this->draft(['implementer_id' => $this->makeUser(Roles::ORG_HEAD, $this->branch)->id])->assertStatus(422)->assertJsonValidationErrors('implementer_id');

        $first = $this->draft()->json('data.id');
        $this->act($first, 'submit');
        $this->act($first, 'approve', [], $this->head)->assertOk();
        $overlap = $this->draft(['title' => 'VLAN qo\'shish', 'planned_start' => '2026-11-21 22:30', 'planned_end' => '2026-11-21 23:30'])->json('data.id');
        $later = $this->draft(['title' => 'NTP sozlash', 'planned_start' => '2026-11-21 23:00', 'planned_end' => '2026-11-21 23:45'])->json('data.id');

        $this->getJson("/api/v1/changes/{$overlap}")->assertOk()->assertJsonCount(1, 'data.conflicts')->assertJsonPath('data.conflicts.0.id', $first);
        $this->getJson("/api/v1/changes/{$later}")->assertOk()->assertJsonCount(0, 'data.conflicts');
        $this->assertCount(3, $this->getJson('/api/v1/changes?filter[asset]='.$this->router->id)->json('data'));
        $this->getJson('/api/v1/changes?filter[status]=approved')->assertJsonCount(1, 'data');
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()))->getJson("/api/v1/changes/{$first}")->assertNotFound();
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->branch))->getJson('/api/v1/changes')->assertForbidden();
    }
}
