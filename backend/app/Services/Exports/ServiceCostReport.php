<?php

namespace App\Services\Exports;

use App\Models\User;
use App\Services\Telecom\BillingService;
use App\Services\Telecom\TelecomReports;

/** Service costs of a year per month (invoiced amount where entered, else planned), grouped. */
class ServiceCostReport implements ReportDefinition
{
    public function __construct(private readonly TelecomReports $reports) {}

    public function permission(): string
    {
        return 'services.export';
    }

    public function filterNames(): array
    {
        return ['year', 'group_by', 'type', 'organization', 'provider'];
    }

    public function title(string $locale): string
    {
        return __('exports.service_costs.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        $months = array_map(fn (int $m) => __("exports.months.{$m}", [], $locale), range(1, 12));

        return [__('exports.service_costs.columns.group', [], $locale), ...$months, __('exports.service_costs.columns.total', [], $locale)];
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $year = (int) ($filters['year'] ?? BillingService::today()->year);
        $group = in_array($filters['group_by'] ?? null, TelecomReports::GROUPS, true) ? (string) $filters['group_by'] : 'organization';
        $costs = $this->reports->costs($user, $year, $group, $filters);
        $cell = fn (array $c) => (float) ($c['invoiced'] !== '0.00' ? $c['invoiced'] : $c['planned']);
        foreach ($costs['rows'] as $row) {
            yield [$row['key']['name'], ...array_map($cell, $row['months']), $cell($row['total'])];
        }
    }
}
