<?php

namespace App\Console\Commands\Helpdesk;

use App\Enums\TicketHistoryAction;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Helpdesk\TicketDueSoonNotification;
use App\Notifications\Helpdesk\TicketSlaBreachedNotification;
use App\Services\Helpdesk\BusinessCalendar;
use App\Services\Helpdesk\HelpdeskRecipients;
use App\Services\Helpdesk\TicketService;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Flags SLA breaches (response, resolution) and sends "due soon" reminders.
 * Each flag is written once, so repeated runs never repeat a notification.
 * Tickets waiting for the user are paused and skipped for resolution checks.
 */
class CheckSlaCommand extends Command
{
    protected $signature = 'helpdesk:check-sla';

    protected $description = 'Flag Helpdesk SLA breaches and send due-soon reminders';

    public function handle(
        TicketService $tickets,
        HelpdeskRecipients $recipients,
        BusinessCalendar $calendar,
        SettingsService $settings,
    ): int {
        $now = CarbonImmutable::now();
        $open = fn (): Builder => Ticket::query()->withoutGlobalScopes()->whereIn('status', TicketStatus::openValues());

        $open()->whereNull('first_response_at')->whereNull('response_breached_at')->where('response_due_at', '<', $now)
            ->chunkById(100, function ($rows) use ($tickets, $recipients, $now): void {
                foreach ($rows as $ticket) {
                    $this->breach($ticket, 'response', $now, $tickets, $recipients);
                }
            });

        $open()->whereNull('paused_at')->whereNull('resolution_breached_at')->where('resolution_due_at', '<', $now)
            ->chunkById(100, function ($rows) use ($tickets, $recipients, $now): void {
                foreach ($rows as $ticket) {
                    $this->breach($ticket, 'resolution', $now, $tickets, $recipients);
                }
            });

        $soonMinutes = (int) $settings->get('helpdesk.due_soon_minutes');
        if ($soonMinutes > 0) {
            $horizon = $calendar->addMinutes($now, $soonMinutes);
            $open()->whereNull('paused_at')->whereNull('resolution_breached_at')->whereNull('due_soon_notified_at')
                ->whereNotNull('assignee_id')
                ->where('resolution_due_at', '>=', $now)->where('resolution_due_at', '<', $horizon)
                ->chunkById(100, function ($rows) use ($now): void {
                    foreach ($rows as $ticket) {
                        $ticket->forceFill(['due_soon_notified_at' => $now])->save();
                        $assignee = User::query()->withoutGlobalScopes()->where('is_active', true)->find($ticket->assignee_id);
                        $assignee?->notify(new TicketDueSoonNotification($ticket));
                    }
                });
        }

        return self::SUCCESS;
    }

    private function breach(Ticket $ticket, string $kind, CarbonImmutable $now, TicketService $tickets, HelpdeskRecipients $recipients): void
    {
        $ticket->forceFill([$kind.'_breached_at' => $now])->save();
        $tickets->history($ticket, null, TicketHistoryAction::SlaBreached, new: ['kind' => $kind]);

        $assignee = $ticket->assignee_id !== null
            ? User::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('is_active', true)->find($ticket->assignee_id)
            : null;
        $to = $assignee !== null ? collect([$assignee]) : $recipients->dispatchers($ticket);
        Notification::send($to, new TicketSlaBreachedNotification($ticket, $kind));
    }
}
