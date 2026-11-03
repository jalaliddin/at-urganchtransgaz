<?php

namespace Tests\Feature\Helpdesk;

use App\Services\Helpdesk\BusinessCalendar;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessCalendarTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Tashkent';

    private function at(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, self::TZ)->utc();
    }

    private function calendar(): BusinessCalendar
    {
        return app(BusinessCalendar::class);
    }

    /** @return array<string, array{string, int, string}> 2026-11-02 is a Monday */
    public static function additions(): array
    {
        return [
            'same morning' => ['2026-11-02 10:00', 120, '2026-11-02 12:00'],
            'across lunch' => ['2026-11-02 12:30', 60, '2026-11-02 14:30'],
            'starts in lunch' => ['2026-11-02 13:20', 30, '2026-11-02 14:30'],
            'friday evening to monday' => ['2026-11-06 17:30', 120, '2026-11-09 10:30'],
            'saturday' => ['2026-11-07 11:00', 30, '2026-11-09 09:30'],
            'before opening' => ['2026-11-02 07:15', 15, '2026-11-02 09:15'],
            'after closing' => ['2026-11-02 19:00', 60, '2026-11-03 10:00'],
            'three business days' => ['2026-11-02 09:00', 1440, '2026-11-04 18:00'],
            'exactly end of day' => ['2026-11-02 17:00', 60, '2026-11-02 18:00'],
            'zero minutes' => ['2026-11-07 11:00', 0, '2026-11-07 11:00'],
        ];
    }

    #[DataProvider('additions')]
    public function test_add_minutes(string $start, int $minutes, string $expected): void
    {
        $result = $this->calendar()->addMinutes($this->at($start), $minutes);

        $this->assertSame('UTC', $result->getTimezone()->getName());
        $this->assertSame($expected, $result->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    public function test_holidays_and_custom_week_are_respected(): void
    {
        app(SettingsService::class)->update([
            'helpdesk.holidays' => ['2026-11-09'],
            'helpdesk.work_days' => [1, 2, 3, 4, 5, 6],
        ]);

        // Friday 17:30 + 120: 30 min Friday, Saturday is now a work day.
        $this->assertSame('2026-11-07 10:30', $this->calendar()
            ->addMinutes($this->at('2026-11-06 17:30'), 120)->setTimezone(self::TZ)->format('Y-m-d H:i'));
        // Saturday 17:30 + 60: Sunday off, Monday holiday → Tuesday.
        $this->assertSame('2026-11-10 09:30', $this->calendar()
            ->addMinutes($this->at('2026-11-07 17:30'), 60)->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    public function test_custom_hours_are_respected(): void
    {
        app(SettingsService::class)->update([
            'helpdesk.work_start' => '08:00', 'helpdesk.work_end' => '17:00',
            'helpdesk.lunch_start' => '12:00', 'helpdesk.lunch_end' => '13:00',
        ]);

        $this->assertSame('2026-11-02 13:30', $this->calendar()
            ->addMinutes($this->at('2026-11-02 11:00'), 90)->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    /** @return array<string, array{string, string, int}> */
    public static function spans(): array
    {
        return [
            'weekend gap' => ['2026-11-06 17:00', '2026-11-09 10:00', 120],
            'over lunch' => ['2026-11-02 12:00', '2026-11-02 15:00', 120],
            'whole day' => ['2026-11-02 00:00', '2026-11-03 00:00', 480],
            'reversed' => ['2026-11-03 10:00', '2026-11-02 10:00', 0],
            'night only' => ['2026-11-02 19:00', '2026-11-03 08:59', 0],
        ];
    }

    #[DataProvider('spans')]
    public function test_minutes_between(string $from, string $to, int $expected): void
    {
        $this->assertSame($expected, $this->calendar()->minutesBetween($this->at($from), $this->at($to)));
    }

    public function test_add_then_measure_round_trips(): void
    {
        $start = $this->at('2026-11-05 16:10');
        $end = $this->calendar()->addMinutes($start, 777);

        $this->assertSame(777, $this->calendar()->minutesBetween($start, $end));
    }
}
