<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketStatsTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Tashkent'));
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->create(['parent_id' => $this->root->id, 'name' => 'Urganch filiali']);
        $this->b = Organization::factory()->create(['parent_id' => $this->root->id, 'name' => 'Xiva filiali']);

        $ra = $this->makeStaff(Roles::EMPLOYEE, $this->a);
        $rb = $this->makeStaff(Roles::EMPLOYEE, $this->b);
        // A: resolved on time (2 business hours), resolved late, open overdue, rejected. B: open, closed.
        $this->state($this->ticketBy($ra, ['priority' => 'critical']), TicketStatus::Resolved, resolvedAfterMinutes: 120, firstResponseAfter: 10);
        $this->state($this->ticketBy($ra, ['priority' => 'critical']), TicketStatus::Resolved, resolvedAfterMinutes: 600, firstResponseAfter: 60);
        $this->state($this->ticketBy($ra, ['priority' => 'critical', 'category_id' => $this->category('network')->id]), TicketStatus::InProgress);
        $this->state($this->ticketBy($ra), TicketStatus::Rejected);
        $this->state($this->ticketBy($rb), TicketStatus::New);
        $this->state($this->ticketBy($rb), TicketStatus::Closed, resolvedAfterMinutes: 60, firstResponseAfter: 5);

        $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00', 'Asia/Tashkent'));
    }

    private function state(Ticket $ticket, TicketStatus $status, ?int $resolvedAfterMinutes = null, ?int $firstResponseAfter = null): void
    {
        $created = $ticket->created_at;
        $ticket->forceFill([
            'status' => $status,
            'first_response_at' => $firstResponseAfter !== null ? $created->addMinutes($firstResponseAfter) : null,
            'resolved_at' => $resolvedAfterMinutes !== null ? $created->addMinutes($resolvedAfterMinutes) : null,
        ])->save();
    }

    public function test_summary_for_unrestricted_viewer(): void
    {
        $this->actingAs($this->makeStaff(Roles::CENTRAL_IT_HEAD, $this->root, true));

        $data = $this->getJson('/api/v1/helpdesk/stats')->assertOk()->json('data');

        $this->assertSame(6, $data['total']);
        $this->assertSame(2, $data['open']);
        $this->assertSame(1, $data['overdue']);
        $this->assertSame(['new' => 1, 'in_review' => 0, 'assigned' => 0, 'in_progress' => 1, 'waiting_user' => 0, 'resolved' => 2, 'closed' => 1, 'rejected' => 1], $data['by_status']);
        $this->assertSame(['low' => 0, 'normal' => 3, 'high' => 0, 'critical' => 3], $data['by_priority']);
        $this->assertSame([['id' => $this->a->id, 'name' => 'Urganch filiali', 'total' => 4, 'open' => 1, 'overdue' => 1], ['id' => $this->b->id, 'name' => 'Xiva filiali', 'total' => 2, 'open' => 1, 'overdue' => 0]], $data['by_organization']);
        $this->assertSame(['response_met' => 2, 'response_total' => 3, 'resolution_met' => 2, 'resolution_total' => 3], $data['sla']);
        // Business minutes to resolve: 120; 10:00→20:00 is 420 (lunch and evening excluded); 60.
        $this->assertSame(200, $data['avg_resolution_minutes']);
        $this->assertSame(['Printer', 5], [$data['by_category'][0]['name'], $data['by_category'][0]['total']]);
    }

    public function test_summary_respects_scope_and_filters(): void
    {
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->a));
        $this->assertSame(4, $this->getJson('/api/v1/helpdesk/stats')->json('data.total'));

        $this->actingAs($this->makeStaff(Roles::CENTRAL_IT_HEAD, $this->root, true));
        $this->assertSame(2, $this->getJson('/api/v1/helpdesk/stats?filter[organization]='.$this->b->id)->json('data.total'));
        $this->assertSame(0, $this->getJson('/api/v1/helpdesk/stats?filter[created_from]=2026-11-03')->json('data.total'));
        $this->assertSame(1, $this->getJson('/api/v1/helpdesk/stats?filter[category]='.$this->category('network')->id)->json('data.total'));
    }

    public function test_requires_view_permission(): void
    {
        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->a));

        $this->getJson('/api/v1/helpdesk/stats')->assertForbidden();
    }

    public function test_dashboard_widgets(): void
    {
        $handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->a);
        Ticket::query()->withoutGlobalScopes()->where('status', 'in_progress')->update(['assignee_id' => $handler->id]);
        $this->actingAs($handler);

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.helpdesk.open', 1)
            ->assertJsonPath('data.helpdesk.overdue', 1)
            ->assertJsonPath('data.helpdesk.assigned_to_me', 1)
            ->assertJsonPath('data.helpdesk.by_status.resolved', 2);

        $employee = $this->makeStaff(Roles::EMPLOYEE, $this->a);
        $this->ticketBy($employee);
        $this->actingAs($employee);
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonMissingPath('data.helpdesk')
            ->assertJsonPath('data.my_tickets.open', 1)
            ->assertJsonPath('data.my_tickets.total', 1);
    }
}
