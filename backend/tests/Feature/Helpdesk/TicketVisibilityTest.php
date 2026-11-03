<?php

namespace Tests\Feature\Helpdesk;

use App\Models\Organization;
use App\Models\UserOrganizationAccess;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketVisibilityTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $org;

    private Organization $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->root = Organization::factory()->create();
        $this->org = Organization::factory()->create(['parent_id' => $this->root->id]);
        $this->other = Organization::factory()->create(['parent_id' => $this->root->id]);
    }

    public function test_employee_sees_only_own_tickets(): void
    {
        $alice = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $bob = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $mine = $this->ticketBy($alice);
        $this->ticketBy($bob);

        $this->actingAs($alice);
        $this->assertSame([$mine->id], collect($this->getJson('/api/v1/helpdesk/tickets')->assertOk()->json('data'))->pluck('id')->all());

        $this->actingAs($bob);
        $this->getJson("/api/v1/helpdesk/tickets/{$mine->id}")->assertNotFound();
    }

    public function test_handlers_see_tickets_of_their_organizations_only(): void
    {
        $ticket = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->org));

        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org));
        $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")->assertOk()->assertJsonPath('data.number', $ticket->number);

        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->other));
        $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/helpdesk/tickets')->json('data'));

        $this->actingAs($this->makeStaff(Roles::AUDITOR, $this->root, true));
        $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")->assertOk();
    }

    public function test_requester_keeps_access_after_moving_to_another_organization(): void
    {
        $alice = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $ticket = $this->ticketBy($alice);
        UserOrganizationAccess::where('user_id', $alice->id)->delete();
        $alice->forceFill(['organization_id' => $this->other->id])->save();
        $this->grantAccess($alice, $this->other);

        $this->actingAs($alice->fresh());
        $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")->assertOk();
    }

    public function test_list_filters_and_search(): void
    {
        $alice = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $a = $this->ticketBy($alice, ['title' => 'Internet sekin', 'category_id' => $this->category('network')->id, 'priority' => 'high']);
        $b = $this->ticketBy($alice, ['title' => 'Printer qog\'oz tortmaydi']);
        $c = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->other), ['title' => 'Parolni unutdim']);
        $handler = $this->makeStaff(Roles::CENTRAL_IT_HEAD, $this->root, true);
        $this->actingAs($handler);

        $ids = fn (string $query) => collect($this->getJson('/api/v1/helpdesk/tickets?'.$query)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$a->id], $ids('filter[priority]=high'));
        $this->assertSame([$a->id], $ids('filter[category]='.$this->category('network')->id));
        $this->assertSame([$a->id, $b->id], $ids('filter[organization]='.$this->org->id));
        $this->assertSame([$c->id], $ids('search=parol'));
        $this->assertSame([$c->id], $ids('search='.$c->number));
        $this->assertSame([$a->id, $b->id, $c->id], $ids('filter[status]=new,in_review'));
        $this->assertSame([], $ids('filter[status]=closed'));
        $this->assertSame([$a->id, $b->id, $c->id], $ids('filter[assignee]=none'));
        $this->assertSame([], $ids('filter[requester]=me'));
        $this->getJson('/api/v1/helpdesk/tickets?filter[unknown]=1')->assertStatus(400);
    }

    public function test_requester_me_filter(): void
    {
        $handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $own = $this->ticketBy($handler);
        $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $this->actingAs($handler);

        $this->assertSame([$own->id], collect($this->getJson('/api/v1/helpdesk/tickets?filter[requester]=me')->json('data'))->pluck('id')->all());
        $this->assertCount(2, $this->getJson('/api/v1/helpdesk/tickets')->json('data'));
    }
}
