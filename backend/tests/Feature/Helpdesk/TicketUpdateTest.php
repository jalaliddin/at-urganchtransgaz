<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TicketHistoryAction;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\TicketHistory;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketUpdateTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->org = Organization::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Tashkent'));
    }

    public function test_requester_edits_while_new_only(): void
    {
        $alice = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $ticket = $this->ticketBy($alice);
        $this->actingAs($alice);

        $this->patchJson("/api/v1/helpdesk/tickets/{$ticket->id}", ['title' => 'Printer umuman ishlamaydi'])
            ->assertOk()->assertJsonPath('data.title', 'Printer umuman ishlamaydi');
        $this->assertSame(TicketHistoryAction::Updated, TicketHistory::where('ticket_id', $ticket->id)->latest('id')->firstOrFail()->action);

        $ticket->forceFill(['status' => TicketStatus::InReview])->save();
        $this->patchJson("/api/v1/helpdesk/tickets/{$ticket->id}", ['title' => 'Yana o\'zgartirish'])->assertForbidden();
    }

    public function test_handler_priority_change_recomputes_due_dates_from_creation(): void
    {
        $ticket = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $this->travel(30)->minutes();
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $this->patchJson("/api/v1/helpdesk/tickets/{$ticket->id}", ['priority' => 'critical'])->assertOk();

        $ticket->refresh();
        $this->assertSame([30, 240], [$ticket->sla_response_minutes, $ticket->sla_resolution_minutes]);
        $this->assertSame('2026-11-02 10:30', $ticket->response_due_at->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-02 15:00', $ticket->resolution_due_at->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_viewer_without_process_cannot_edit(): void
    {
        $ticket = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $this->actingAs($this->makeStaff(Roles::ORG_HEAD, $this->org));

        $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")->assertOk();
        $this->patchJson("/api/v1/helpdesk/tickets/{$ticket->id}", ['priority' => 'low'])->assertForbidden();
    }

    public function test_closed_ticket_cannot_be_edited_by_handler(): void
    {
        $ticket = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $ticket->forceFill(['status' => TicketStatus::Closed])->save();
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org));

        $this->patchJson("/api/v1/helpdesk/tickets/{$ticket->id}", ['priority' => 'low'])->assertForbidden();
    }
}
