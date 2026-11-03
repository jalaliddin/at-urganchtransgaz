<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TicketHistoryAction;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketCreateTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->org = Organization::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-06 17:30', 'Asia/Tashkent'));
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Kompyuter yonmayapti',
            'description' => 'Ertalabdan beri kompyuter yonmayapti, chiroqlar yonmaydi.',
            'category_id' => $this->category()->id,
            'contact_phone' => '+998 90 123-45-67',
            ...$overrides,
        ];
    }

    public function test_employee_files_ticket_with_business_hours_sla(): void
    {
        $user = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/helpdesk/tickets', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.number', 'HD-2026-000001')
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.priority', 'normal')
            ->assertJsonPath('data.organization.id', $this->org->id)
            ->assertJsonPath('data.requester.employee_id', $user->employee_id);

        $ticket = Ticket::findOrFail($response->json('data.id'));
        $this->assertSame($user->id, $ticket->requester_user_id);
        $this->assertSame($user->id, $ticket->created_by);
        $this->assertSame([120, 1440], [$ticket->sla_response_minutes, $ticket->sla_resolution_minutes]);
        // Friday 17:30 + 120 business minutes = Monday 10:30.
        $this->assertSame('2026-11-09 10:30', $ticket->response_due_at->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-11 17:30', $ticket->resolution_due_at->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
        $this->assertSame(TicketHistoryAction::Created, TicketHistory::where('ticket_id', $ticket->id)->firstOrFail()->action);
    }

    public function test_handler_files_on_behalf_of_colleague(): void
    {
        $handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $colleague = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $this->actingAs($handler);

        $id = $this->postJson('/api/v1/helpdesk/tickets', $this->payload([
            'requester_employee_id' => $colleague->employee_id, 'priority' => 'critical',
        ]))->assertCreated()->json('data.id');

        $ticket = Ticket::findOrFail($id);
        $this->assertSame($colleague->id, $ticket->requester_user_id);
        $this->assertSame($handler->id, $ticket->created_by);
        $this->assertSame(30, $ticket->sla_response_minutes);
    }

    public function test_employee_cannot_file_for_someone_else(): void
    {
        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $other = Employee::factory()->create(['organization_id' => $this->org->id]);

        $this->postJson('/api/v1/helpdesk/tickets', $this->payload(['requester_employee_id' => $other->id]))
            ->assertStatus(422)->assertJsonValidationErrors('requester_employee_id');
    }

    public function test_requester_must_belong_to_ticket_organization(): void
    {
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $stranger = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->postJson('/api/v1/helpdesk/tickets', $this->payload(['requester_employee_id' => $stranger->id]))
            ->assertStatus(422)->assertJsonValidationErrors('requester_employee_id');
    }

    public function test_out_of_scope_organization_and_inactive_category_are_rejected(): void
    {
        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $this->category('server')->update(['is_active' => false]);

        $this->postJson('/api/v1/helpdesk/tickets', $this->payload(['organization_id' => Organization::factory()->create()->id]))
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $this->postJson('/api/v1/helpdesk/tickets', $this->payload(['category_id' => $this->category('server')->id]))
            ->assertStatus(422)->assertJsonValidationErrors('category_id');
    }

    public function test_validation_messages(): void
    {
        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->org));

        $this->postJson('/api/v1/helpdesk/tickets', ['title' => 'ab', 'contact_phone' => 'call me', 'contact_email' => 'x', 'priority' => 'urgent'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'category_id', 'contact_phone', 'contact_email', 'priority']);
    }

    public function test_user_without_create_permission_gets_403(): void
    {
        $this->actingAs($this->makeStaff(Roles::AUDITOR, $this->org));

        $this->postJson('/api/v1/helpdesk/tickets', $this->payload())->assertForbidden();
    }

    public function test_user_without_home_organization_must_choose_one(): void
    {
        $user = $this->makeUser(Roles::CENTRAL_IT_HEAD);
        $this->actingAs($user);

        $this->postJson('/api/v1/helpdesk/tickets', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');
    }

    public function test_category_with_tickets_cannot_be_deleted(): void
    {
        $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->deleteJson('/api/v1/ticket-categories/'.$this->category('printer')->id)
            ->assertStatus(409)->assertJsonPath('code', 'conflict');
    }
}
