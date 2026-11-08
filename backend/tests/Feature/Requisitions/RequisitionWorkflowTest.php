<?php

namespace Tests\Feature\Requisitions;

use App\Enums\RequisitionStatus;
use App\Models\Organization;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\Requisitions\RequisitionNotification;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesRequisitions;
use Tests\Concerns\CreatesWarehouse;
use Tests\Concerns\InteractsWithAccess;
use Tests\Feature\Files\SafeUploadTest;
use Tests\TestCase;

class RequisitionWorkflowTest extends TestCase
{
    use CreatesRequisitions, CreatesWarehouse, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private Organization $branch;

    private User $it;

    private User $orgHead;

    private User $staff;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWarehouse();
        $this->center = Organization::factory()->create();
        $this->branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->branch);
        $this->orgHead = $this->makeUser(Roles::ORG_HEAD, $this->branch);
        $this->staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->center, true);
        $this->grantAccess($this->staff, $this->branch);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->grantAccess($this->head, $this->branch);
    }

    private function act(User $user, Requisition|int $requisition, string $action, array $payload = []): TestResponse
    {
        $id = $requisition instanceof Requisition ? $requisition->id : $requisition;

        return $this->actingAs($user)->postJson("/api/v1/requisitions/{$id}/{$action}", $payload);
    }

    private function statusOf(int $id): string
    {
        return Requisition::query()->withoutGlobalScopes()->findOrFail($id)->status->value;
    }

    public function test_drafts_are_created_by_org_it_and_seen_only_by_their_requester(): void
    {
        $this->actingAs($this->it);
        $id = $this->postJson('/api/v1/requisitions', [
            'organization_id' => $this->branch->id, 'title' => 'Printer uchun kartrij', 'needed_by' => now()->addWeek()->toDateString(),
            'items' => [
                ['kind' => 'consumable', 'material_id' => $this->material()->id, 'name' => 'HP 85A', 'quantity' => 3],
                ['kind' => 'sim', 'name' => 'Korporativ SIM', 'quantity' => 2],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.items.1.kind', 'sim')
            ->assertJsonPath('data.can.submit', true)
            ->json('data.id');
        $this->assertMatchesRegularExpression('/^RQ-\d{4}-00001$/', Requisition::query()->withoutGlobalScopes()->findOrFail($id)->number);
        $this->putJson("/api/v1/requisitions/{$id}", ['title' => 'Kartrij (shoshilinch)', 'items' => [['kind' => 'consumable', 'name' => 'HP 85A', 'quantity' => 5]]])
            ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.quantity', '5.000');
        $this->postJson('/api/v1/requisitions', ['organization_id' => $this->center->id, 'title' => 'X', 'items' => [['kind' => 'other', 'name' => 'x', 'quantity' => 1]]])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');

        $this->actingAs($this->staff)->getJson("/api/v1/requisitions/{$id}")->assertNotFound();
        $this->actingAs($this->orgHead)->getJson('/api/v1/requisitions')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->branch))->postJson('/api/v1/requisitions', [])->assertForbidden();

        $this->actingAs($this->it)->deleteJson("/api/v1/requisitions/{$id}")->assertOk();
        $this->assertNull(Requisition::query()->withoutGlobalScopes()->find($id));
    }

    public function test_head_approval_then_central_review_with_an_information_round_trip(): void
    {
        Notification::fake();
        $r = $this->draftFor($this->branch, $this->it);

        $this->act($this->it, $r, 'submit')->assertOk()->assertJsonPath('data.status', 'org_approval');
        Notification::assertSentTo($this->orgHead, RequisitionNotification::class);
        $this->act($this->it, $r, 'org-decision', ['decision' => 'approved'])->assertForbidden();
        $this->act($this->makeUser(Roles::ORG_HEAD, Organization::factory()->create()), $r, 'org-decision', ['decision' => 'approved'])->assertNotFound();
        $this->act($this->head, $r, 'decision', ['decision' => 'approved'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

        $this->act($this->orgHead, $r, 'org-decision', ['decision' => 'approved', 'comment' => 'Kerak'])->assertOk()->assertJsonPath('data.status', 'submitted');
        Notification::assertSentTo($this->staff, RequisitionNotification::class);

        $this->act($this->staff, $r, 'take')->assertOk()->assertJsonPath('data.status', 'central_review')->assertJsonPath('data.reviewer.id', $this->staff->id);
        $this->act($this->staff, $r, 'request-info')->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->act($this->staff, $r, 'request-info', ['comment' => 'Monitor qaysi xodim uchun?'])->assertOk()->assertJsonPath('data.status', 'info_requested');
        Notification::assertSentTo($this->it, RequisitionNotification::class, fn ($n) => $n->event === 'info_requested');
        $this->act($this->staff, $r, 'provide-info', ['comment' => 'x'])->assertForbidden();
        $this->act($this->it, $r, 'provide-info', ['comment' => 'Buxgalteriya uchun'])->assertOk()->assertJsonPath('data.status', 'central_review');

        $this->act($this->staff, $r, 'decision', ['decision' => 'approved'])->assertForbidden();
        $items = $r->items()->pluck('id')->all();
        $this->act($this->head, $r, 'decision', ['decision' => 'approved', 'items' => [['id' => $items[0], 'approved_quantity' => 5]]])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.approved_quantity');
        $this->act($this->head, $r, 'decision', ['decision' => 'approved', 'comment' => 'Qisqartirildi', 'items' => [['id' => $items[0], 'approved_quantity' => 2]]])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.items.0.approved_quantity', '2.000')
            ->assertJsonPath('data.items.1.approved_quantity', '1.000');

        $detail = $this->actingAs($this->it)->getJson("/api/v1/requisitions/{$r->id}")->assertOk()->json('data');
        $this->assertSame(['org_head:approved', 'central_head:approved'], array_map(fn ($a) => $a['stage'].':'.$a['decision'], $detail['approvals']));
        $this->assertSame(
            ['created', 'submitted', 'org_approved', 'taken', 'info_requested', 'info_provided', 'approved'],
            array_column($detail['history'], 'action'),
        );
    }

    public function test_head_approval_is_skipped_without_an_approver_or_when_switched_off(): void
    {
        $lonely = Organization::factory()->create();
        $it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $lonely);
        $r = $this->draftFor($lonely, $it);
        $this->act($it, $r, 'submit')->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->assertContains('approval_skipped', $r->histories()->pluck('action')->map->value->all());

        app(SettingsService::class)->update(['requisitions.head_approval' => false]);
        $other = $this->draftFor($this->branch, $this->it);
        $this->act($this->it, $other, 'submit')->assertOk()->assertJsonPath('data.status', 'submitted');
    }

    public function test_rejection_needs_a_reason_and_return_sends_back_to_the_requester(): void
    {
        app(SettingsService::class)->update(['requisitions.head_approval' => false]);
        $r = $this->draftFor($this->branch, $this->it);
        $this->act($this->it, $r, 'submit');

        $this->act($this->head, $r, 'decision', ['decision' => 'returned', 'comment' => 'Asoslang'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->actingAs($this->it)->putJson("/api/v1/requisitions/{$r->id}", ['description' => 'Eski monitor buzildi'])->assertOk();
        $this->act($this->it, $r, 'submit')->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->act($this->head, $r, 'decision', ['decision' => 'rejected'])->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->act($this->head, $r, 'decision', ['decision' => 'rejected', 'comment' => 'Byudjet yo\'q'])->assertOk()
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection_reason', 'Byudjet yo\'q');
        $this->act($this->it, $r, 'submit')->assertStatus(422);
        $this->actingAs($this->it)->deleteJson("/api/v1/requisitions/{$r->id}")->assertStatus(422);
    }

    public function test_requester_cannot_approve_their_own_requisition(): void
    {
        app(SettingsService::class)->update(['requisitions.head_approval' => false]);
        $r = $this->draftFor($this->branch, $this->head);
        $this->act($this->head, $r, 'submit')->assertOk();

        $this->act($this->head, $r, 'decision', ['decision' => 'approved'])->assertForbidden();
    }

    public function test_org_head_can_return_or_reject(): void
    {
        $r = $this->draftFor($this->branch, $this->it);
        $this->act($this->it, $r, 'submit');
        $this->act($this->orgHead, $r, 'org-decision', ['decision' => 'returned', 'comment' => 'Miqdorni kamaytiring'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->act($this->it, $r, 'submit');
        $this->act($this->orgHead, $r, 'org-decision', ['decision' => 'rejected', 'comment' => 'Kerak emas'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame(RequisitionStatus::Rejected->value, $this->statusOf($r->id));
    }

    public function test_list_tabs_comments_and_attachments(): void
    {
        Storage::fake('local');
        app(SettingsService::class)->update(['requisitions.head_approval' => false]);
        $r = $this->draftFor($this->branch, $this->it);
        $this->draftFor($this->branch, $this->it, null, ['title' => 'Qoralama']);
        $this->act($this->it, $r, 'submit');

        $this->actingAs($this->staff)->getJson('/api/v1/requisitions?filter[tab]=action')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->it)->getJson('/api/v1/requisitions?filter[tab]=mine')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->it)->getJson('/api/v1/requisitions?filter[status]=draft')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->staff)->postJson("/api/v1/requisitions/{$r->id}/comments", ['body' => 'Omborda bor'])->assertCreated();
        $uuid = $this->actingAs($this->it)->post("/api/v1/requisitions/{$r->id}/attachments",
            ['files' => [UploadedFile::fake()->createWithContent('asos.pdf', SafeUploadTest::pdf())]], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.0.file.uuid');
        $this->actingAs($this->staff)->get("/api/v1/files/{$uuid}")->assertOk();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()))
            ->get("/api/v1/files/{$uuid}", ['Accept' => 'application/json'])->assertNotFound();

        $detail = $this->actingAs($this->it)->getJson("/api/v1/requisitions/{$r->id}")->json('data');
        $this->assertSame('Omborda bor', $detail['comments'][0]['body']);
        $this->assertCount(1, $detail['attachments']);
    }
}
