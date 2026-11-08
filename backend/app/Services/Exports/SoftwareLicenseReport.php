<?php

namespace App\Services\Exports;

use App\Models\Scopes\OrganizationScope;
use App\Models\SoftwareLicense;
use App\Models\User;

/** Licences of the user's organizations with seat usage (never the key). */
class SoftwareLicenseReport implements ReportDefinition
{
    public function permission(): string
    {
        return 'software.export';
    }

    public function filterNames(): array
    {
        return ['software', 'expiring'];
    }

    public function title(string $locale): string
    {
        return __('exports.software_licenses.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.software_licenses.columns.{$k}", [], $locale),
            ['software', 'number', 'organization', 'seats', 'used', 'free', 'valid_from', 'expires_at', 'supplier', 'cost']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $scope = $user->accessScope();
        $query = SoftwareLicense::query()->withoutGlobalScope(OrganizationScope::class)->with(['software', 'organization'])->withCount('installations')
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn('organization_id', $scope->organizationIds() ?: [0]))
            ->when(! empty($filters['software']), fn ($q) => $q->where('software_id', (int) $filters['software']))
            ->when(filter_var($filters['expiring'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays((int) config('software.license_notice_days'))->toDateString()))
            ->orderBy('expires_at');
        foreach ($query->get() as $l) {
            $used = (int) $l->getAttribute('installations_count');
            yield [
                $l->software->name, $l->number, $l->organization->name, $l->seats, $used, max(0, $l->seats - $used),
                $l->valid_from?->format('d.m.Y'), $l->expires_at?->format('d.m.Y'), $l->supplier, $l->cost === null ? null : (float) $l->cost,
            ];
        }
    }
}
