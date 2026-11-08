<?php

namespace App\Services\Exports;

use App\Models\User;
use App\Services\Software\ComplianceService;

/** Devices with antivirus status and missing required software (same filters as the compliance screen). */
class SoftwareComplianceReport implements ReportDefinition
{
    public function __construct(private readonly ComplianceService $compliance) {}

    public function permission(): string
    {
        return 'software.export';
    }

    public function filterNames(): array
    {
        return ['organization', 'problem'];
    }

    public function title(string $locale): string
    {
        return __('exports.software_compliance.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.software_compliance.columns.{$k}", [], $locale),
            ['inventory_number', 'name', 'organization', 'antivirus', 'product', 'missing', 'inventoried_at']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $problem = $filters['problem'] ?? null;
        $tz = (string) config('app.display_timezone');
        foreach ($this->compliance->devices($user, $filters) as $d) {
            if (in_array($problem, ComplianceService::PROBLEMS, true) && ! ComplianceService::hasProblem($d, (string) $problem)) {
                continue;
            }
            yield [
                $d['asset']->inventory_number, $d['asset']->name, $d['asset']->organization->name,
                __('enums.antivirus_effective.'.$d['antivirus']->value, [], $locale), $d['product'],
                implode(', ', $d['missing']) ?: null, $d['asset']->software_inventoried_at?->setTimezone($tz)->format('d.m.Y H:i'),
            ];
        }
    }
}
