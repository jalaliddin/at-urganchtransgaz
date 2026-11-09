<?php

namespace App\Services\Exports;

use App\Enums\SimStatus;
use App\Models\Scopes\OrganizationScope;
use App\Models\SimCard;
use App\Models\User;
use App\Support\OrganizationSubtree;

/** SIM cards of the user's organizations; the ICCID only as its last four digits. */
class SimCardReport implements ReportDefinition
{
    public function permission(): string
    {
        return 'sim.export';
    }

    public function filterNames(): array
    {
        return ['provider', 'status', 'organization'];
    }

    public function title(string $locale): string
    {
        return __('exports.sim_cards.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.sim_cards.columns.{$k}", [], $locale),
            ['phone', 'operator', 'organization', 'department', 'employee', 'status', 'tariff', 'account', 'monthly_fee', 'allocated_at', 'returned_at', 'iccid']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $scope = $user->accessScope();
        $status = SimStatus::tryFrom((string) ($filters['status'] ?? ''));
        $query = SimCard::query()->withoutGlobalScope(OrganizationScope::class)->with(['provider', 'organization', 'department', 'employee'])
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn('organization_id', $scope->organizationIds() ?: [0]))
            ->when(! empty($filters['provider']), fn ($q) => $q->where('provider_id', (int) $filters['provider']))
            ->when($status !== null, fn ($q) => $q->where('status', $status?->value))
            ->when(! empty($filters['organization']), fn ($q) => $q->whereIn('organization_id', OrganizationSubtree::ids((int) $filters['organization']) ?: [0]))
            ->orderBy('phone_number');
        foreach ($query->lazy(500) as $c) {
            yield [
                $c->phone_number, $c->provider->name, $c->organization->name, $c->department?->name, $c->employee?->full_name,
                __('enums.sim_status.'.$c->status->value, [], $locale), $c->tariff, $c->account_number,
                $c->monthly_fee === null ? null : (float) $c->monthly_fee, $c->allocated_at?->format('d.m.Y'), $c->returned_at?->format('d.m.Y'),
                $c->maskedIccid(),
            ];
        }
    }
}
