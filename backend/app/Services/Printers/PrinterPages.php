<?php

namespace App\Services\Printers;

use App\Models\Asset;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Page maths of design P7: pages of a reading = total − previous total of the
 * same printer (by date, then entry order); after a reset = total; the first
 * reading is the baseline (no pages). Pages of a period = sum over its readings.
 */
class PrinterPages
{
    /**
     * Readings with their `pages`, over all readings of the given printers (the
     * previous reading may lie before any period the caller filters on).
     *
     * @param  list<int>|null  $assetIds  null = every printer
     */
    public function readings(?array $assetIds = null): Builder
    {
        $inner = DB::table('printer_counters')
            ->when($assetIds !== null, fn (Builder $q) => $q->whereIn('asset_id', $assetIds === [] ? [0] : $assetIds))
            ->selectRaw('id, asset_id, organization_id, read_at, total, is_reset, CASE WHEN is_reset = 1 THEN CAST(total AS SIGNED)
                ELSE GREATEST(CAST(total AS SIGNED) - CAST(LAG(total) OVER (PARTITION BY asset_id ORDER BY read_at, id) AS SIGNED), 0) END AS pages');

        return DB::query()->fromSub($inner, 'readings');
    }

    /**
     * Pages per month for the last $months months ending with today's month; null
     * when no reading of that month has pages.
     *
     * @return list<array{month: string, pages: int|null}>
     */
    public function monthly(Asset $printer, CarbonImmutable $today, int $months = 12): array
    {
        $start = $today->startOfMonth()->subMonths($months - 1);
        $sums = $this->readings([$printer->id])->where('read_at', '>=', $start->toDateString())
            ->where('read_at', '<=', $today->endOfMonth()->toDateString())
            ->groupByRaw("DATE_FORMAT(read_at, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(read_at, '%Y-%m') AS month, SUM(pages) AS pages")
            ->pluck('pages', 'month');

        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonths($i)->format('Y-m');
            $pages = $sums[$month] ?? null;
            $rows[] = ['month' => $month, 'pages' => $pages === null ? null : (int) $pages];
        }

        return $rows;
    }
}
