<?php

namespace App\Services\Exports;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceType;
use App\Models\ItService;
use App\Models\Scopes\OrganizationScope;
use App\Models\ServiceInvoice;
use App\Models\User;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;

/** Service invoices and payment control of the user's organizations. */
class ServiceInvoiceReport implements ReportDefinition
{
    public function permission(): string
    {
        return 'services.export';
    }

    public function filterNames(): array
    {
        return ['status', 'organization', 'period', 'provider', 'type'];
    }

    public function title(string $locale): string
    {
        return __('exports.service_invoices.title', [], $locale);
    }

    public function columns(string $locale): array
    {
        return array_map(fn (string $k) => __("exports.service_invoices.columns.{$k}", [], $locale),
            ['organization', 'service', 'type', 'provider', 'period', 'invoice_number', 'invoice_date', 'planned', 'amount', 'paid', 'due_date', 'status', 'approved']);
    }

    public function rows(User $user, array $filters, string $locale): iterable
    {
        $scope = $user->accessScope();
        $status = InvoiceStatus::tryFrom((string) ($filters['status'] ?? ''));
        $type = ServiceType::tryFrom((string) ($filters['type'] ?? ''));
        $period = (string) ($filters['period'] ?? '');
        $query = ServiceInvoice::query()->withoutGlobalScope(OrganizationScope::class)->with(['service.provider', 'organization'])
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn('organization_id', $scope->organizationIds() ?: [0]))
            ->when($status !== null, fn ($q) => $q->where('status', $status?->value))
            ->when(! empty($filters['organization']), fn ($q) => $q->whereIn('organization_id', OrganizationSubtree::ids((int) $filters['organization']) ?: [0]))
            ->when($type !== null || ! empty($filters['provider']), fn ($q) => $q->whereIn('service_id', ItService::query()->withoutGlobalScopes()->select('id')
                ->when($type !== null, fn ($s) => $s->where('type', $type?->value))
                ->when(! empty($filters['provider']), fn ($s) => $s->where('provider_id', (int) $filters['provider']))))
            ->when(preg_match('/^\d{4}-\d{2}$/', $period) === 1, function ($q) use ($period) {
                $month = CarbonImmutable::parse($period.'-01');
                $q->where('period_start', '<=', $month->endOfMonth()->toDateString())->where('period_end', '>=', $month->toDateString());
            })
            ->orderBy('due_date')->orderBy('id');
        foreach ($query->lazy(500) as $i) {
            yield [
                $i->organization->name, $i->service->name, __('enums.service_type.'.$i->service->type->value, [], $locale), $i->service->provider->name,
                $i->period_start->format('d.m.Y').' – '.$i->period_end->format('d.m.Y'), $i->invoice_number, $i->invoice_date?->format('d.m.Y'),
                (float) $i->planned_amount, $i->amount === null ? null : (float) $i->amount, (float) $i->paid_amount, $i->due_date->format('d.m.Y'),
                __('enums.invoice_status.'.$i->status->value, [], $locale), $i->approved_at?->format('d.m.Y'),
            ];
        }
    }
}
