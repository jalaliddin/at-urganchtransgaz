<?php

namespace App\Services\Tasks;

use App\Enums\RecurrenceFrequency;
use App\Models\TaskRecurrence;
use Carbon\CarbonImmutable;

/** Occurrence dates of a recurrence: daily every N days, weekly on a weekday every N weeks, monthly on a day every N months. */
class RecurrenceSchedule
{
    /** First occurrence on or after $from. */
    public function first(TaskRecurrence $rule, CarbonImmutable $from): CarbonImmutable
    {
        $from = $from->startOfDay();

        return match ($rule->frequency) {
            RecurrenceFrequency::Daily => $from,
            RecurrenceFrequency::Weekly => $from->addDays(((int) $rule->weekday - $from->dayOfWeekIso + 7) % 7),
            RecurrenceFrequency::Monthly => $from->day <= (int) $rule->month_day
                ? $from->setDay((int) $rule->month_day)
                : $from->startOfMonth()->addMonthNoOverflow()->setDay((int) $rule->month_day),
        };
    }

    /** The occurrence after $date (which is itself an occurrence). */
    public function advance(TaskRecurrence $rule, CarbonImmutable $date): CarbonImmutable
    {
        $interval = max(1, $rule->interval);

        return match ($rule->frequency) {
            RecurrenceFrequency::Daily => $date->addDays($interval),
            RecurrenceFrequency::Weekly => $date->addWeeks($interval),
            RecurrenceFrequency::Monthly => $date->startOfMonth()->addMonthsNoOverflow($interval)->setDay((int) $rule->month_day),
        };
    }
}
