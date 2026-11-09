<?php

namespace App\Services\Exports;

use App\Models\User;
use App\Services\Printers\PrinterReports;

/** Cartridge consumption, cost and pages per organization, cartridge model or printer (design P9). */
class PrinterConsumptionReport implements ReportDefinition
{
    public function __construct(private readonly PrinterReports $reports) {}

    public function permission(): string
    {
        return 'printers.export';
    }

    public function filterNames(): array
    {
        return ['date_from', 'date_to', 'group_by', 'organization'];
    }

    public function title(string $locale): string
    {
        return __('exports.printer_consumption.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.printer_consumption.columns.{$k}", [], $locale),
            ['key', 'replacements', 'cost', 'pages', 'cost_per_page']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        foreach ($this->reports->report($user, $filters)['rows'] as $row) {
            $name = $row['key']['code'] === null ? $row['key']['name'] : $row['key']['code'].' — '.$row['key']['name'];
            yield [$name, $row['replacements'], $row['cost'], $row['pages'], $row['cost_per_page']];
        }
    }
}
