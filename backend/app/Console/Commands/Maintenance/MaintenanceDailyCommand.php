<?php

namespace App\Console\Commands\Maintenance;

use App\Services\Maintenance\MaintenanceService;
use Illuminate\Console\Command;

/** Preventive maintenance: open the next work items and send "due soon" / "overdue" reminders once (design M2). */
class MaintenanceDailyCommand extends Command
{
    protected $signature = 'maintenance:daily';

    protected $description = 'Creates preventive maintenance work items and sends their reminders';

    public function handle(MaintenanceService $maintenance): int
    {
        $maintenance->daily($maintenance->today());

        return self::SUCCESS;
    }
}
