<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Helpdesk\TicketDueSoonNotification;
use App\Notifications\Helpdesk\TicketSlaBreachedNotification;
use App\Notifications\Helpdesk\TicketStatusChangedNotification;
use App\Support\Permissions\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class SlaCommandsTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $requester;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->org = Organization::factory()->create();
        $this->requester = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $this->dispatcher = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Tashkent'));
        Notification::fake();
    }

    private function at(string $local): void
    {
        $this->travelTo(CarbonImmutable::parse($local, 'Asia/Tashkent'));
    }

    public function test_response_breach_is_flagged_once_and_reported_to_dispatchers(): void
    {
        $ticket = $this->ticketBy($this->requester); // normal: response due 12:00
        $this->at('2026-11-02 11:59');
        $this->artisan('helpdesk:check-sla')->assertSuccessful();
        $this->assertNull($ticket->fresh()->response_breached_at);

        $this->at('2026-11-02 12:01');
        $this->artisan('helpdesk:check-sla')->assertSuccessful();
        $this->artisan('helpdesk:check-sla')->assertSuccessful();

        $this->assertNotNull($ticket->fresh()->response_breached_at);
        Notification::assertSentToTimes($this->dispatcher, TicketSlaBreachedNotification::class, 1);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'sla_breached']);
    }

    public function test_resolution_breach_goes_to_assignee_and_skips_paused_tickets(): void
    {
        $assigned = $this->ticketBy($this->requester, ['priority' => 'critical']); // resolution due 15:00
        $assigned->forceFill(['assignee_id' => $this->dispatcher->id, 'status' => TicketStatus::InProgress, 'first_response_at' => now()])->save();
        $paused = $this->ticketBy($this->requester, ['priority' => 'critical']);
        $paused->forceFill(['status' => TicketStatus::WaitingUser, 'paused_at' => now(), 'first_response_at' => now()])->save();

        $this->at('2026-11-02 15:30');
        $this->artisan('helpdesk:check-sla')->assertSuccessful();

        $this->assertNotNull($assigned->fresh()->resolution_breached_at);
        $this->assertNull($paused->fresh()->resolution_breached_at);
        Notification::assertSentTo($this->dispatcher, TicketSlaBreachedNotification::class,
            fn ($n, $c, $u) => $n->toArray($u)['params']['kind'] === 'resolution');
    }

    public function test_due_soon_reminder_once(): void
    {
        $ticket = $this->ticketBy($this->requester, ['priority' => 'critical']); // resolution due 15:00
        $ticket->forceFill(['assignee_id' => $this->dispatcher->id, 'status' => TicketStatus::InProgress, 'first_response_at' => now()])->save();

        $this->at('2026-11-02 13:30'); // lunch: 60 business minutes left
        $this->artisan('helpdesk:check-sla')->assertSuccessful();
        Notification::assertNotSentTo($this->dispatcher, TicketDueSoonNotification::class);

        $this->at('2026-11-02 14:05');
        $this->artisan('helpdesk:check-sla')->assertSuccessful();
        $this->artisan('helpdesk:check-sla')->assertSuccessful();
        Notification::assertSentToTimes($this->dispatcher, TicketDueSoonNotification::class, 1);
    }

    public function test_auto_close_after_business_days(): void
    {
        $ticket = $this->ticketBy($this->requester);
        $ticket->forceFill(['status' => TicketStatus::Resolved, 'resolved_at' => now(), 'resolution' => 'OK'])->save();

        $this->at('2026-11-04 18:00'); // 2.75 business days later
        $this->artisan('helpdesk:auto-close')->assertSuccessful();
        $this->assertSame(TicketStatus::Resolved, $ticket->fresh()->status);

        $this->at('2026-11-05 10:01'); // 3 business days + 1 minute
        $this->artisan('helpdesk:auto-close')->assertSuccessful();
        $fresh = $ticket->fresh();
        $this->assertSame(TicketStatus::Closed, $fresh->status);
        $this->assertNotNull($fresh->closed_at);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'auto_closed']);
        Notification::assertSentTo($this->requester, TicketStatusChangedNotification::class);
    }

    public function test_commands_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command);

        $this->assertTrue($events->contains(fn ($c) => str_contains((string) $c, 'helpdesk:check-sla')));
        $this->assertTrue($events->contains(fn ($c) => str_contains((string) $c, 'helpdesk:auto-close')));
    }
}
