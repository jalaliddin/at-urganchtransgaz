<?php

namespace Tests\Feature\Helpdesk;

use App\Models\Organization;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketLinkTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    public function test_linking_rules(): void
    {
        $this->seedHelpdesk();
        $org = Organization::factory()->create();
        $requester = $this->makeStaff(Roles::EMPLOYEE, $org);
        $handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $org);
        $a = $this->ticketBy($requester);
        $b = $this->ticketBy($requester);
        $foreign = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, Organization::factory()->create()));

        $this->actingAs($requester);
        $this->postJson("/api/v1/helpdesk/tickets/{$a->id}/links", ['linked_ticket_id' => $b->id, 'type' => 'relates'])->assertForbidden();

        $this->actingAs($handler);
        $this->postJson("/api/v1/helpdesk/tickets/{$a->id}/links", ['linked_ticket_id' => $a->id, 'type' => 'relates'])
            ->assertStatus(422)->assertJsonValidationErrors('linked_ticket_id');
        $this->postJson("/api/v1/helpdesk/tickets/{$a->id}/links", ['linked_ticket_id' => $foreign->id, 'type' => 'relates'])
            ->assertStatus(422)->assertJsonValidationErrors('linked_ticket_id');
        $this->postJson("/api/v1/helpdesk/tickets/{$a->id}/links", ['linked_ticket_id' => $b->id, 'type' => 'other'])
            ->assertStatus(422)->assertJsonValidationErrors('type');

        $linkId = $this->postJson("/api/v1/helpdesk/tickets/{$a->id}/links", ['linked_ticket_id' => $b->id, 'type' => 'duplicates'])
            ->assertCreated()->assertJsonPath('data.ticket.number', $b->number)->json('data.id');
        $this->postJson("/api/v1/helpdesk/tickets/{$b->id}/links", ['linked_ticket_id' => $a->id, 'type' => 'relates'])->assertStatus(409);

        $this->getJson("/api/v1/helpdesk/tickets/{$b->id}/links")->assertOk()
            ->assertJsonPath('data.0.ticket.number', $a->number)->assertJsonPath('data.0.type', 'duplicates');
        $this->actingAs($requester);
        $this->getJson("/api/v1/helpdesk/tickets/{$a->id}/links")->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($handler);
        $this->deleteJson("/api/v1/helpdesk/tickets/{$b->id}/links/{$linkId}")->assertOk();
        $this->assertDatabaseCount('ticket_links', 0);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $b->id, 'action' => 'unlinked']);
    }
}
