<?php

namespace App\Services\Maintenance;

use App\Enums\MaintenanceUnit;
use Carbon\CarbonImmutable;

/**
 * Due dates of a plan: start + k × interval (months never drift: 31 Jan → 28 Feb → 31 Mar). The next date comes after
 * the previous one and is not before today — missed cycles are not created (design M3).
 */
class MaintenanceSchedule
{
    private const MAX_STEPS = 100000;

    public function next(CarbonImmutable $start, int $count, MaintenanceUnit $unit, ?CarbonImmutable $previous, CarbonImmutable $today): CarbonImmutable
    {
        $after = $previous?->toDateString();
        $floor = $today->toDateString();
        $k = $this->estimate($start, $count, $unit, max($after ?? $floor, $floor));
        for ($i = 0; $i < self::MAX_STEPS; $i++, $k++) {
            $date = $this->occurrence($start, $count, $unit, $k);
            if (($after === null || $date->toDateString() > $after) && $date->toDateString() >= $floor) {
                return $date;
            }
        }

        return $this->occurrence($start, $count, $unit, $k);
    }

    public function occurrence(CarbonImmutable $start, int $count, MaintenanceUnit $unit, int $k): CarbonImmutable
    {
        return $unit === MaintenanceUnit::Month ? $start->addMonthsNoOverflow($k * $count) : $start->addWeeks($k * $count);
    }

    /** A step index a little before $from, so the search loop stays short. */
    private function estimate(CarbonImmutable $start, int $count, MaintenanceUnit $unit, string $from): int
    {
        $target = CarbonImmutable::parse($from);
        if ($target <= $start) {
            return 0;
        }
        $span = $unit === MaintenanceUnit::Month ? (int) $start->diffInMonths($target) : (int) $start->diffInWeeks($target);

        return max(0, intdiv($span, max(1, $count)) - 1);
    }
}
