<?php

namespace Tests\Unit;

use App\Enums\MaintenanceUnit;
use App\Services\Maintenance\MaintenanceSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MaintenanceScheduleTest extends TestCase
{
    private function at(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    public function test_monthly_dates_stay_anchored_to_the_start_day(): void
    {
        $schedule = new MaintenanceSchedule;
        $start = $this->at('2026-01-31');

        $this->assertSame('2026-01-31', $schedule->next($start, 1, MaintenanceUnit::Month, null, $this->at('2026-01-10'))->toDateString());
        $this->assertSame('2026-02-28', $schedule->next($start, 1, MaintenanceUnit::Month, $this->at('2026-01-31'), $this->at('2026-01-10'))->toDateString());
        $this->assertSame('2026-03-31', $schedule->next($start, 1, MaintenanceUnit::Month, $this->at('2026-02-28'), $this->at('2026-02-10'))->toDateString());
        $this->assertSame('2026-07-31', $schedule->next($start, 3, MaintenanceUnit::Month, $this->at('2026-04-30'), $this->at('2026-05-01'))->toDateString());
    }

    public function test_missed_cycles_are_skipped_up_to_today(): void
    {
        $schedule = new MaintenanceSchedule;
        $start = $this->at('2026-11-02');

        $this->assertSame('2026-11-30', $schedule->next($start, 2, MaintenanceUnit::Week, null, $this->at('2026-11-20'))->toDateString());
        $this->assertSame('2026-11-16', $schedule->next($start, 2, MaintenanceUnit::Week, $this->at('2026-11-02'), $this->at('2026-11-10'))->toDateString());
        $this->assertSame('2027-01-11', $schedule->next($start, 2, MaintenanceUnit::Week, $this->at('2026-11-30'), $this->at('2027-01-10'))->toDateString());
        $this->assertSame('2027-01-11', $schedule->next($start, 2, MaintenanceUnit::Week, $this->at('2026-11-30'), $this->at('2027-01-11'))->toDateString());
    }
}
