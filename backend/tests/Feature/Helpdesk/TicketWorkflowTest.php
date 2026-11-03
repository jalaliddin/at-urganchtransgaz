<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    /** Handler map from the spec §4 (assignment goes through /assign). */
    private const HANDLER = [
        'new' => ['in_review', 'in_progress', 'rejected'],
        'in_review' => ['in_progress', 'rejected'],
        'assigned' => ['in_progress', 'waiting_user', 'resolved'],
        'in_progress' => ['waiting_user', 'resolved'],
        'waiting_user' => ['in_progress', 'resolved'],
        'resolved' => ['in_progress', 'closed'],
        'closed' => ['in_progress'],
        'rejected' => [],
    ];

    private Organization $org;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->org = Organization::factory()->create();
        $this->requester = $this->makeStaff(Roles::EMPLOYEE, $this->org);
    }

    private function ticketIn(TicketStatus $status): Ticket
    {
        $ticket = $this->ticketBy($this->requester);
        $ticket->forceFill([
            'status' => $status,
            'paused_at' => $status === TicketStatus::WaitingUser ? now() : null,
            'resolved_at' => in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true) ? now() : null,
            'closed_at' => $status === TicketStatus::Closed ? now() : null,
        ])->save();

        return $ticket;
    }

    /** @return array<string, array{string, string, bool}> */
    public static function pairs(): array
    {
        $rows = [];
        foreach (self::HANDLER as $from => $allowed) {
            foreach (TicketStatus::cases() as $to) {
                if ($to->value !== $from) {
                    $rows["{$from} → {$to->value}"] = [$from, $to->value, in_array($to->value, $allowed, true)];
                }
            }
        }

        return $rows;
    }

    #[DataProvider('pairs')]
    public function test_handler_transition_map(string $from, string $to, bool $allowed): void
    {
        $ticket = $this->ticketIn(TicketStatus::from($from));
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $response = $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/transition", ['status' => $to, 'note' => 'Izoh matni']);

        if ($allowed) {
            $response->assertOk()->assertJsonPath('data.status', $to);
        } else {
            $response->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        }
    }

    public function test_requester_closes_and_reopens_but_cannot_work_the_ticket(): void
    {
        $this->actingAs($this->requester);

        $resolved = $this->ticketIn(TicketStatus::Resolved);
        $this->postJson("/api/v1/helpdesk/tickets/{$resolved->id}/transition", ['status' => 'closed'])
            ->assertOk()->assertJsonPath('data.status', 'closed');

        $this->postJson("/api/v1/helpdesk/tickets/{$resolved->id}/transition", ['status' => 'in_progress', 'note' => 'Yana takrorlandi'])
            ->assertOk()->assertJsonPath('data.reopened_count', 1)->assertJsonPath('data.closed_at', null);

        $new = $this->ticketIn(TicketStatus::New);
        $this->postJson("/api/v1/helpdesk/tickets/{$new->id}/transition", ['status' => 'in_progress'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_viewer_who_is_neither_requester_nor_handler_is_forbidden(): void
    {
        $ticket = $this->ticketIn(TicketStatus::New);
        $this->actingAs($this->makeStaff(Roles::ORG_HEAD, $this->org));

        $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/transition", ['status' => 'in_review'])->assertForbidden();
    }

    public function test_resolve_and_reject_require_a_note(): void
    {
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $ticket = $this->ticketIn(TicketStatus::InProgress);

        $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/transition", ['status' => 'resolved'])
            ->assertStatus(422)->assertJsonValidationErrors('note');
        $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/transition", ['status' => 'resolved', 'note' => 'Kartrij almashtirildi'])
            ->assertOk()->assertJsonPath('data.resolution', 'Kartrij almashtirildi');

        $other = $this->ticketIn(TicketStatus::New);
        $this->postJson("/api/v1/helpdesk/tickets/{$other->id}/transition", ['status' => 'rejected'])
            ->assertStatus(422)->assertJsonValidationErrors('note');
        $this->postJson("/api/v1/helpdesk/tickets/{$other->id}/transition", ['status' => 'rejected', 'note' => 'Takroriy ariza'])
            ->assertOk()->assertJsonPath('data.reject_reason', 'Takroriy ariza');
    }

    public function test_reopen_window(): void
    {
        $ticket = $this->ticketIn(TicketStatus::Closed);
        $ticket->forceFill(['resolved_at' => now()->subDays(8), 'closed_at' => now()->subDays(8)])->save();
        $this->actingAs($this->requester);

        $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/transition", ['status' => 'in_progress'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_starting_work_on_unassigned_ticket_assigns_the_handler(): void
    {
        $handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $ticket = $this->ticketIn(TicketStatus::New);
        $this->actingAs($handler);

        $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/transition", ['status' => 'in_progress'])
            ->assertOk()->assertJsonPath('data.assignee.id', $handler->id);
    }

    public function test_allowed_transitions_are_exposed(): void
    {
        $ticket = $this->ticketIn(TicketStatus::InProgress);
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertJsonPath('data.allowed_transitions', ['waiting_user', 'resolved']);

        $this->actingAs($this->requester);
        $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")->assertJsonPath('data.allowed_transitions', []);
    }
}
