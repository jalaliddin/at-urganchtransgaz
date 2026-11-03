<?php

namespace App\Services\Helpdesk;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * HD-YYYY-NNNNNN numbers from a per-year counter row locked FOR UPDATE, so
 * concurrent requests never share a number. The year follows the display time zone.
 */
class TicketNumberGenerator
{
    public function next(CarbonInterface $at): string
    {
        $year = (int) $at->copy()->setTimezone((string) config('app.display_timezone'))->format('Y');

        return DB::transaction(function () use ($year): string {
            DB::table('ticket_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
            $current = (int) DB::table('ticket_counters')->where('year', $year)->lockForUpdate()->value('last_number');
            $next = $current + 1;
            DB::table('ticket_counters')->where('year', $year)->update(['last_number' => $next]);

            return sprintf('HD-%d-%06d', $year, $next);
        });
    }
}
