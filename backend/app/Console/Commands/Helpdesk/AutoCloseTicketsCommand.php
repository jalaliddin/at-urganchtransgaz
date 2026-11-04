<?php

namespace App\Console\Commands\Helpdesk;

use App\Enums\TicketHistoryAction;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Helpdesk\BusinessCalendar;
use App\Services\Helpdesk\TicketWorkflow;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Closes resolved tickets the requester did not confirm within helpdesk.auto_close_days business days. */
class AutoCloseTicketsCommand extends Command
{
    protected $signature = 'helpdesk:auto-close';

    protected $description = 'Close resolved Helpdesk tickets after the confirmation period';

    public function handle(TicketWorkflow $workflow, BusinessCalendar $calendar, SettingsService $settings): int
    {
        $days = (int) $settings->get('helpdesk.auto_close_days');
        $threshold = $days * $calendar->dailyMinutes();
        if ($days === 0 || $threshold === 0) {
            return self::SUCCESS;
        }

        $now = CarbonImmutable::now();
        Ticket::query()->withoutGlobalScopes()
            ->where('status', TicketStatus::Resolved->value)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $now->subDays($days))
            ->chunkById(100, function ($rows) use ($workflow, $calendar, $threshold, $now): void {
                foreach ($rows as $ticket) {
                    if ($calendar->minutesBetween($ticket->resolved_at, $now) >= $threshold) {
                        DB::transaction(fn () => $workflow->applyStatus($ticket, TicketStatus::Closed, null, null, TicketHistoryAction::AutoClosed));
                    }
                }
            });

        return self::SUCCESS;
    }
}
