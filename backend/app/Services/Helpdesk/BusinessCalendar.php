<?php

namespace App\Services\Helpdesk;

use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Business-time arithmetic for SLA deadlines. Working windows come from the
 * helpdesk.* settings (work days, hours, lunch, holidays) and are evaluated in
 * the display time zone; results are returned in UTC.
 */
class BusinessCalendar
{
    /** Upper bound on days scanned, so a calendar without working time cannot loop forever. */
    private const MAX_DAYS = 3700;

    public function __construct(private readonly SettingsService $settings) {}

    /** The moment $minutes business minutes after $start. Zero or negative returns $start. */
    public function addMinutes(CarbonInterface $start, int $minutes): CarbonImmutable
    {
        if ($minutes <= 0) {
            return CarbonImmutable::instance($start)->utc();
        }

        $config = $this->config();
        $cursor = CarbonImmutable::instance($start)->setTimezone($this->timezone());
        $remaining = $minutes * 60;

        for ($day = 0; $day < self::MAX_DAYS; $day++) {
            foreach ($this->windows($cursor, $config) as [$open, $close]) {
                if ($cursor->greaterThanOrEqualTo($close)) {
                    continue;
                }
                $from = $cursor->greaterThan($open) ? $cursor : $open;
                $available = $close->getTimestamp() - $from->getTimestamp();
                if ($remaining <= $available) {
                    return $from->addSeconds($remaining)->utc();
                }
                $remaining -= $available;
                $cursor = $close;
            }
            $cursor = $cursor->startOfDay()->addDay();
        }

        throw new RuntimeException('The business calendar has no working time.');
    }

    /** Business minutes in [$from, $to); 0 when $to is not after $from. */
    public function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $tz = $this->timezone();
        $start = CarbonImmutable::instance($from)->setTimezone($tz);
        $end = CarbonImmutable::instance($to)->setTimezone($tz);
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $config = $this->config();
        $seconds = 0;
        $day = $start->startOfDay();
        for ($i = 0; $i < self::MAX_DAYS && $day->lessThan($end); $i++) {
            foreach ($this->windows($day, $config) as [$open, $close]) {
                $a = max($open->getTimestamp(), $start->getTimestamp());
                $b = min($close->getTimestamp(), $end->getTimestamp());
                if ($b > $a) {
                    $seconds += $b - $a;
                }
            }
            $day = $day->addDay();
        }

        return intdiv($seconds, 60);
    }

    /** Business minutes in one ordinary working day (used for "N business days"). */
    public function dailyMinutes(): int
    {
        $config = $this->config();
        $toMinutes = fn (string $t): int => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
        [$start, $end] = [$toMinutes($config['start']), $toMinutes($config['end'])];
        [$lunchStart, $lunchEnd] = [$toMinutes($config['lunch_start']), $toMinutes($config['lunch_end'])];
        $lunch = max(0, min($lunchEnd, $end) - max($lunchStart, $start));

        return max(0, $end - $start - $lunch);
    }

    /**
     * Working windows of the local day containing $moment.
     *
     * @param  array{days: list<int>, holidays: array<string, true>, start: string, end: string, lunch_start: string, lunch_end: string}  $config
     * @return list<array{CarbonImmutable, CarbonImmutable}>
     */
    private function windows(CarbonImmutable $moment, array $config): array
    {
        $day = $moment->startOfDay();
        if (! in_array($day->isoWeekday(), $config['days'], true) || isset($config['holidays'][$day->format('Y-m-d')])) {
            return [];
        }

        $at = fn (string $time): CarbonImmutable => $day->setTimeFromTimeString($time);
        $open = $at($config['start']);
        $close = $at($config['end']);
        $lunchStart = $at($config['lunch_start']);
        $lunchEnd = $at($config['lunch_end']);

        if ($lunchEnd->lessThanOrEqualTo($lunchStart)) {
            $windows = [[$open, $close]];
        } else {
            $windows = [
                [$open, $lunchStart->lessThan($close) ? $lunchStart : $close],
                [$lunchEnd->greaterThan($open) ? $lunchEnd : $open, $close],
            ];
        }

        return array_values(array_filter($windows, fn (array $w): bool => $w[0]->lessThan($w[1])));
    }

    /** @return array{days: list<int>, holidays: array<string, true>, start: string, end: string, lunch_start: string, lunch_end: string} */
    private function config(): array
    {
        $all = $this->settings->all();

        return [
            'days' => array_map('intval', (array) $all['helpdesk.work_days']),
            'holidays' => array_fill_keys(array_map('strval', (array) $all['helpdesk.holidays']), true),
            'start' => (string) $all['helpdesk.work_start'],
            'end' => (string) $all['helpdesk.work_end'],
            'lunch_start' => (string) $all['helpdesk.lunch_start'],
            'lunch_end' => (string) $all['helpdesk.lunch_end'],
        ];
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', 'Asia/Tashkent');
    }
}
