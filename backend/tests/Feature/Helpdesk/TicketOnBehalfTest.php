<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Helpdesk\TicketCommentedNotification;
use App\Notifications\Helpdesk\TicketStatusChangedNotification;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

/** A handler filing a ticket for a colleague stays a handler; the colleague is the requester. */
class TicketOnBehalfTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private User $colleague;

    private User $handler;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $org = Organization::factory()->create();
        $this->colleague = $this->makeStaff(Roles::EMPLOYEE, $org);
        $this->handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $org);
        Notification::fake();
        $this->ticket = $this->ticketBy($this->handler, ['requester_employee_id' => $this->colleague->employee_id]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/v1/helpdesk/tickets/{$this->ticket->id}{$suffix}";
    }

    public function test_handler_actions_count_as_first_response_and_reach_the_requester(): void
    {
        $this->actingAs($this->handler);
        $this->postJson($this->url('/take'))->assertOk();
        $this->postJson($this->url('/comments'), ['body' => 'Kelyapman.'])->assertCreated();

        $this->assertNotNull($this->ticket->fresh()->first_response_at);
        Notification::assertSentTo($this->colleague, TicketStatusChangedNotification::class);
        Notification::assertSentTo($this->colleague, TicketCommentedNotification::class);
    }

    public function test_only_the_real_requester_rates(): void
    {
        $this->ticket->forceFill(['status' => TicketStatus::Resolved, 'resolved_at' => now(), 'assignee_id' => $this->handler->id])->save();

        $this->actingAs($this->handler);
        $this->assertNotContains('rate', $this->getJson($this->url())->json('data.allowed_actions'));
        $this->assertFalse($this->getJson($this->url())->json('data.is_requester'));
        $this->postJson($this->url('/rate'), ['rating' => 5])->assertForbidden();

        $this->actingAs($this->colleague);
        $this->postJson($this->url('/rate'), ['rating' => 4])->assertOk();
    }

    public function test_handler_comment_does_not_end_the_wait_for_the_user(): void
    {
        $this->ticket->forceFill(['status' => TicketStatus::WaitingUser, 'paused_at' => now(), 'assignee_id' => $this->handler->id])->save();

        $this->actingAs($this->handler);
        $this->postJson($this->url('/comments'), ['body' => 'Eslatma: javobingizni kutyapmiz.'])->assertCreated();

        $this->assertSame(TicketStatus::WaitingUser, $this->ticket->fresh()->status);
    }

    public function test_filer_still_sees_the_ticket_in_my_tickets(): void
    {
        $this->actingAs($this->handler);

        $this->assertSame([$this->ticket->id], collect($this->getJson('/api/v1/helpdesk/tickets?filter[requester]=me')->json('data'))->pluck('id')->all());
    }
}
