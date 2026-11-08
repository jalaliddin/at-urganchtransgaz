<?php

namespace App\Services\Exports;

use App\Models\SoftwareInstallation;
use App\Models\User;
use App\Support\OrganizationSubtree;

/** Software installed on the user's devices. */
class SoftwareInstallationReport implements ReportDefinition
{
    public function permission(): string
    {
        return 'software.export';
    }

    public function filterNames(): array
    {
        return ['software', 'organization', 'unrecognized'];
    }

    public function title(string $locale): string
    {
        return __('exports.software_installations.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.software_installations.columns.{$k}", [], $locale),
            ['inventory_number', 'device', 'organization', 'software', 'vendor', 'version', 'source', 'license', 'last_seen_at']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $tz = (string) config('app.display_timezone');
        $query = SoftwareInstallation::query()->visibleTo($user)->with(['asset.organization', 'software', 'license']);
        if (! empty($filters['software'])) {
            $query->where('software_id', (int) $filters['software']);
        }
        if (filter_var($filters['unrecognized'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('software_id');
        }
        if (! empty($filters['organization'])) {
            $query->whereHas('asset', fn ($q) => $q->withoutGlobalScopes()->whereIn('organization_id', OrganizationSubtree::ids((int) $filters['organization']) ?: [0]));
        }
        foreach ($query->lazyById(500, 'software_installations.id', 'id') as $i) {
            /** @var SoftwareInstallation $i */
            yield [
                $i->asset->inventory_number, $i->asset->name, $i->asset->organization->name, $i->software->name ?? $i->name,
                $i->vendor, $i->version, __('enums.installation_source.'.$i->source->value, [], $locale), $i->license?->number,
                $i->last_seen_at?->setTimezone($tz)->format('d.m.Y H:i'),
            ];
        }
    }
}
