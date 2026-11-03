<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketActionsTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $org;

    private User $requester;

    private User $handler;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Tashkent'));
        $this->root = Organization::factory()->create();
        $this->org = Organization::factory()->create(['parent_id' => $this->root->id]);
        $this->requester = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $this->handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->ticket = $this->ticketBy($this->requester);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/helpdesk/tickets/{$this->ticket->id}{$suffix}";
    }

    public function test_assign_validates_assignee(): void
    {
        $staffElsewhere = $this->makeStaff(Roles::CENTRAL_IT_STAFF, Organization::factory()->create());
        $employee = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $central = $this->makeStaff(Roles::CENTRAL_IT_STAFF, $this->root, true);
        $this->actingAs($this->handler);

        $this->postJson($this->url('/assign'), ['assignee_id' => $staffElsewhere->id])->assertStatus(422)->assertJsonValidationErrors('assignee_id');
        $this->postJson($this->url('/assign'), ['assignee_id' => $employee->id])->assertStatus(422)->assertJsonValidationErrors('assignee_id');

        $this->postJson($this->url('/assign'), ['assignee_id' => $central->id])
            ->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.assignee.id', $central->id);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $this->ticket->id, 'action' => 'assigned']);
    }

    public function test_assign_and_take_permissions(): void
    {
        $central = $this->makeStaff(Roles::CENTRAL_IT_STAFF, $this->root, true);

        $this->actingAs($this->requester);
        $this->postJson($this->url('/assign'), ['assignee_id' => $this->handler->id])->assertForbidden();
        $this->postJson($this->url('/take'))->assertForbidden();

        $this->actingAs($central);
        $this->postJson($this->url('/assign'), ['assignee_id' => $this->handler->id])->assertForbidden();
        $this->postJson($this->url('/take'))->assertOk()->assertJsonPath('data.assignee.id', $central->id);
    }

    public function test_assignees_endpoint_lists_handlers_with_access(): void
    {
        $central = $this->makeStaff(Roles::CENTRAL_IT_STAFF, $this->root, true);
        $this->makeStaff(Roles::CENTRAL_IT_STAFF, Organization::factory()->create());
        $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $this->actingAs($this->handler);

        $ids = collect($this->getJson('/api/v1/helpdesk/assignees?organization_id='.$this->org->id)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$this->handler->id, $central->id])->sort()->values()->all(), $ids);
        $this->actingAs($this->requester);
        $this->getJson('/api/v1/helpdesk/assignees?organization_id='.$this->org->id)->assertForbidden();
    }

    public function test_waiting_for_user_pauses_the_resolution_clock(): void
    {
        $this->assertSame('2026-11-05 10:00', $this->ticket->resolution_due_at->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
        $this->actingAs($this->handler);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 11:00', 'Asia/Tashkent'));
        $this->postJson($this->url('/transition'), ['status' => 'in_progress'])->assertOk();
        $this->postJson($this->url('/transition'), ['status' => 'waiting_user', 'note' => 'Qaysi xonada?'])->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-03 11:00', 'Asia/Tashkent'));
        $this->actingAs($this->requester);
        $this->postJson($this->url('/comments'), ['body' => '214-xona, ikkinchi qavat.'])->assertCreated();

        $ticket = $this->ticket->fresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->paused_at);
        $this->assertSame(480, $ticket->paused_minutes);
        $this->assertSame('2026-11-06 10:00', $ticket->resolution_due_at->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_first_response_is_the_first_public_handler_action(): void
    {
        $this->actingAs($this->requester);
        $this->postJson($this->url('/comments'), ['body' => 'Iltimos, tezroq.'])->assertCreated();
        $this->actingAs($this->handler);
        $this->postJson($this->url('/comments'), ['body' => 'Ichki eslatma', 'is_internal' => true])->assertCreated();
        $this->assertNull($this->ticket->fresh()->first_response_at);

        $this->travel(20)->minutes();
        $this->postJson($this->url('/comments'), ['body' => 'Ko\'rib chiqyapmiz.'])->assertCreated();
        $first = $this->ticket->fresh()->first_response_at;
        $this->assertNotNull($first);

        $this->travel(20)->minutes();
        $this->postJson($this->url('/comments'), ['body' => 'Yana bir izoh.'])->assertCreated();
        $this->assertTrue($first->equalTo($this->ticket->fresh()->first_response_at));
    }

    public function test_internal_comments_are_hidden_from_requester(): void
    {
        $this->actingAs($this->handler);
        $this->postJson($this->url('/comments'), ['body' => 'Ichki eslatma', 'is_internal' => true])->assertCreated();
        $this->postJson($this->url('/comments'), ['body' => 'Ochiq javob'])->assertCreated();
        $this->assertCount(2, $this->getJson($this->url('/comments'))->json('data'));

        $this->actingAs($this->requester);
        $this->assertSame(['Ochiq javob'], collect($this->getJson($this->url('/comments'))->assertOk()->json('data'))->pluck('body')->all());
        $history = collect($this->getJson($this->url('/history'))->assertOk()->json('data'));
        $this->assertFalse($history->contains(fn ($h) => $h['is_internal']));
        $this->assertTrue($history->contains(fn ($h) => $h['action'] === 'created'));

        $this->postJson($this->url('/comments'), ['body' => 'Yashirin', 'is_internal' => true])->assertForbidden();
    }

    public function test_only_participants_comment(): void
    {
        $this->actingAs($this->makeStaff(Roles::ORG_HEAD, $this->org));
        $this->postJson($this->url('/comments'), ['body' => 'Rahbar izohi'])->assertForbidden();

        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $this->postJson($this->url('/comments'), ['body' => 'Begona'])->assertNotFound();

        $this->actingAs($this->requester);
        $this->postJson($this->url('/comments'), ['body' => ''])->assertStatus(422);
    }

    public function test_rating_by_requester_after_resolution(): void
    {
        $this->actingAs($this->requester);
        $this->postJson($this->url('/rate'), ['rating' => 5])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');

        $this->ticket->forceFill(['status' => TicketStatus::Resolved, 'resolved_at' => now()])->save();
        $this->postJson($this->url('/rate'), ['rating' => 6])->assertStatus(422)->assertJsonValidationErrors('rating');
        $this->postJson($this->url('/rate'), ['rating' => 4, 'comment' => 'Tez hal qilindi'])
            ->assertOk()->assertJsonPath('data.rating', 4)->assertJsonPath('data.rating_comment', 'Tez hal qilindi');

        $this->actingAs($this->handler);
        $this->postJson($this->url('/rate'), ['rating' => 5])->assertForbidden();
    }

    public function test_time_logging_by_handler(): void
    {
        $this->actingAs($this->handler);
        $this->postJson($this->url('/time'), ['minutes' => 30, 'note' => 'Diagnostika'])->assertOk()->assertJsonPath('data.time_spent_minutes', 30);
        $this->postJson($this->url('/time'), ['minutes' => 45])->assertOk()->assertJsonPath('data.time_spent_minutes', 75);
        $this->postJson($this->url('/time'), ['minutes' => 0])->assertStatus(422);

        $this->actingAs($this->requester);
        $this->postJson($this->url('/time'), ['minutes' => 10])->assertForbidden();
    }

    public function test_allowed_actions_per_role(): void
    {
        $this->actingAs($this->handler);
        $this->assertEqualsCanonicalizing(
            ['update', 'comment', 'comment_internal', 'assign', 'take', 'log_time', 'link', 'attach'],
            $this->getJson($this->url())->json('data.allowed_actions'),
        );

        $this->actingAs($this->requester);
        $this->assertEqualsCanonicalizing(['update', 'comment', 'attach'], $this->getJson($this->url())->json('data.allowed_actions'));
    }
}
