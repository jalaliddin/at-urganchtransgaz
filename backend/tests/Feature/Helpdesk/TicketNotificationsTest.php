<?php

namespace Tests\Feature\Helpdesk;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\Channels\ChannelResolver;
use App\Notifications\Helpdesk\TicketAssignedNotification;
use App\Notifications\Helpdesk\TicketCommentedNotification;
use App\Notifications\Helpdesk\TicketCreatedNotification;
use App\Notifications\Helpdesk\TicketStatusChangedNotification;
use App\Services\Settings\SettingsService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketNotificationsTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $org;

    private User $requester;

    private User $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->root = Organization::factory()->create();
        $this->org = Organization::factory()->create(['parent_id' => $this->root->id]);
        $this->requester = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $this->dispatcher = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
        Notification::fake();
    }

    public function test_new_ticket_notifies_dispatchers_with_access(): void
    {
        $head = $this->makeStaff(Roles::CENTRAL_IT_HEAD, $this->root, true);
        $elsewhere = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create());
        $colleague = $this->makeStaff(Roles::EMPLOYEE, $this->org);

        $ticket = $this->ticketBy($this->requester);

        Notification::assertSentTo([$this->dispatcher, $head], TicketCreatedNotification::class,
            fn ($n, $channels, $notifiable) => $n->toArray($notifiable)['params']['number'] === $ticket->number);
        Notification::assertNotSentTo([$elsewhere, $colleague, $this->requester], TicketCreatedNotification::class);
    }

    public function test_dispatcher_filing_a_ticket_is_not_notified_about_it(): void
    {
        $this->ticketBy($this->dispatcher);

        Notification::assertNotSentTo($this->dispatcher, TicketCreatedNotification::class);
    }

    public function test_assignment_status_and_comment_notifications(): void
    {
        $ticket = $this->ticketBy($this->requester);
        $staff = $this->makeStaff(Roles::CENTRAL_IT_STAFF, $this->root, true);

        $this->actingAs($this->dispatcher)->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/assign", ['assignee_id' => $staff->id])->assertOk();
        Notification::assertSentTo($staff, TicketAssignedNotification::class);

        $this->actingAs($staff)->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/transition", ['status' => 'in_progress'])->assertOk();
        Notification::assertSentTo($this->requester, TicketStatusChangedNotification::class,
            fn ($n, $c, $u) => $n->toArray($u)['params']['status'] === 'in_progress');
        Notification::assertNotSentTo($staff, TicketStatusChangedNotification::class);

        $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/comments", ['body' => 'Ichki', 'is_internal' => true])->assertCreated();
        Notification::assertNotSentTo($this->requester, TicketCommentedNotification::class);

        $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/comments", ['body' => 'Javob'])->assertCreated();
        Notification::assertSentTo($this->requester, TicketCommentedNotification::class);

        $this->actingAs($this->requester)->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/comments", ['body' => 'Rahmat'])->assertCreated();
        Notification::assertSentTo($staff, TicketCommentedNotification::class);
    }

    public function test_take_does_not_notify_the_taker(): void
    {
        $ticket = $this->ticketBy($this->requester);

        $this->actingAs($this->dispatcher)->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/take")->assertOk();

        Notification::assertNotSentTo($this->dispatcher, TicketAssignedNotification::class);
        Notification::assertSentTo($this->requester, TicketStatusChangedNotification::class);
    }

    public function test_requester_reopen_notifies_assignee(): void
    {
        $ticket = $this->ticketBy($this->requester);
        $ticket->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'assignee_id' => $this->dispatcher->id])->save();

        $this->actingAs($this->requester)->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/transition", ['status' => 'in_progress'])->assertOk();

        Notification::assertSentTo($this->dispatcher, TicketStatusChangedNotification::class);
    }

    public function test_channels_follow_settings_preferences_and_email(): void
    {
        $resolver = app(ChannelResolver::class);
        $user = $this->requester;
        $user->forceFill(['email' => 'ali@urtg.uz'])->save();

        $this->assertSame(['database', 'mail'], $resolver->for($user, 'ticket_commented'));

        $user->forceFill(['notification_preferences' => ['mail_muted' => ['ticket_commented']]])->save();
        $this->assertSame(['database'], $resolver->for($user->fresh(), 'ticket_commented'));
        $this->assertSame(['database', 'mail'], $resolver->for($user->fresh(), 'ticket_assigned'));

        app(SettingsService::class)->update(['notifications.mail_enabled' => false]);
        $this->assertSame(['database'], $resolver->for($user->fresh(), 'ticket_assigned'));

        $user->forceFill(['email' => null])->save();
        app(SettingsService::class)->update(['notifications.mail_enabled' => true]);
        $this->assertSame(['database'], $resolver->for($user->fresh(), 'ticket_assigned'));
    }

    public function test_payload_and_mail_rendering(): void
    {
        $ticket = $this->ticketBy($this->requester);
        $this->requester->forceFill(['email' => 'ali@urtg.uz', 'locale' => 'ru'])->save();
        $notification = new TicketAssignedNotification($ticket, 'Karimov A.');

        $payload = $notification->toArray($this->requester);
        $this->assertSame('ticket_assigned', $payload['type']);
        $this->assertSame('notifications.ticket_assigned', $payload['title_key']);
        $this->assertSame("/helpdesk/{$ticket->id}", $payload['url']);
        $this->assertSame($ticket->number, $payload['params']['number']);

        $mail = $notification->toMail($this->requester->fresh());
        $this->assertStringContainsString($ticket->number, (string) $mail->subject);
        $this->assertStringContainsString('/helpdesk/'.$ticket->id, (string) $mail->actionUrl);
    }

    public function test_profile_saves_muted_mail_types(): void
    {
        $this->actingAs($this->requester);

        $this->patchJson('/api/v1/auth/me', ['notification_preferences' => ['mail_muted' => ['ticket_commented']]])
            ->assertOk()->assertJsonPath('data.notification_preferences.mail_muted', ['ticket_commented']);
        $this->patchJson('/api/v1/auth/me', ['notification_preferences' => ['mail_muted' => ['bogus']]])
            ->assertStatus(422);
        $this->patchJson('/api/v1/auth/me', ['locale' => 'ru'])->assertOk()
            ->assertJsonPath('data.notification_preferences.mail_muted', ['ticket_commented']);
    }
}
