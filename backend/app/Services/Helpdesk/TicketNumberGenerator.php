<?php

namespace App\Services\Helpdesk;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * HD-YYYY-NNNNNN numbers from a per-year counter row. Call inside the ticket's
 * transaction: the row stays locked until commit and a rollback returns the number,
 * so numbers are unique and gap-free. The year follows the display time zone.
 */
class TicketNumberGenerator
{
    public function next(CarbonInterface $at): string
    {
        $year = (int) $at->copy()->setTimezone((string) config('app.display_timezone'))->format('Y');

        // One statement takes the row's exclusive lock and increments it. (INSERT IGNORE
        // followed by SELECT … FOR UPDATE upgrades a shared lock and deadlocks under load.)
        // LAST_INSERT_ID(expr) hands the new value back on this connection only.
        $connection = DB::connection();
        $connection->statement(
            'INSERT INTO ticket_counters (year, last_number) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)',
            [$year],
        );
        $next = (int) $connection->selectOne('SELECT LAST_INSERT_ID() AS n')->n;

        return sprintf('HD-%d-%06d', $year, $next);
    }
}
