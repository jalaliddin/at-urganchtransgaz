<?php

namespace App\Services\Telecom;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceType;
use App\Enums\SimStatus;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\ServiceContract;
use App\Models\ServiceInvoice;
use App\Models\ServiceProvider;
use App\Models\SimCard;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Decimal;
use App\Support\OrganizationSubtree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * SIM and service cost figures for reports, exports and the dashboard. Scope is applied explicitly (works in
 * queued exports without an authenticated user).
 */
class TelecomReports
{
    public const GROUPS = ['organization', 'provider', 'type'];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Cards that are not cancelled, per organization and operator.
     *
     * @param  array<string, mixed>  $filters  organization, provider
     * @return array{rows: list<array<string, mixed>>, total: array<string, mixed>}
     */
    public function simSummary(User $user, array $filters): array
    {
        $query = $this->scoped(SimCard::query(), $user, $filters['organization'] ?? null)
            ->where('status', '!=', SimStatus::Cancelled->value)
            ->when(! empty($filters['provider']), fn ($q) => $q->where('provider_id', (int) $filters['provider']))
            ->toBase()->select(['organization_id', 'provider_id'])->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(monthly_fee), 0) AS monthly_fee')
            ->groupBy('organization_id', 'provider_id');
        foreach ([SimStatus::InStock, SimStatus::Active, SimStatus::Blocked, SimStatus::Returned] as $status) {
            $query->selectRaw('SUM(status = ?) AS '.$status->value, [$status->value]);
        }
        $raw = $query->get();
        $organizations = Organization::query()->withoutGlobalScopes()->whereIn('id', $raw->pluck('organization_id'))->pluck('name', 'id');
        $providers = ServiceProvider::query()->whereIn('id', $raw->pluck('provider_id'))->pluck('name', 'id');
        $rows = $raw->map(fn (object $r) => [
            'organization' => ['id' => (int) $r->organization_id, 'name' => (string) $organizations->get($r->organization_id)],
            'provider' => ['id' => (int) $r->provider_id, 'name' => (string) $providers->get($r->provider_id)],
            'total' => (int) $r->total, 'in_stock' => (int) $r->in_stock, 'active' => (int) $r->active,
            'blocked' => (int) $r->blocked, 'returned' => (int) $r->returned, 'monthly_fee' => Decimal::of((string) $r->monthly_fee, 2),
        ])->sortBy(fn (array $r) => $r['organization']['name'].'|'.$r['provider']['name'])->values();
        $sum = fn (string $key) => $rows->sum($key);

        return ['rows' => $rows->all(), 'total' => [
            'total' => $sum('total'), 'in_stock' => $sum('in_stock'), 'active' => $sum('active'), 'blocked' => $sum('blocked'),
            'returned' => $sum('returned'),
            'monthly_fee' => $rows->reduce(fn (string $c, array $r) => Decimal::add($c, $r['monthly_fee'], 2), '0.00'),
        ]];
    }

    /**
     * Planned, invoiced and paid amounts per month of $year (by billing period start), grouped.
     *
     * @param  array<string, mixed>  $filters  organization, provider, type
     * @return array{rows: list<array<string, mixed>>, total: array<string, string>}
     */
    public function costs(User $user, int $year, string $groupBy, array $filters): array
    {
        $key = match ($groupBy) {
            'provider' => 's.provider_id',
            'type' => 's.type',
            default => 'service_invoices.organization_id',
        };
        $type = ServiceType::tryFrom((string) ($filters['type'] ?? ''));
        $raw = $this->scoped(ServiceInvoice::query(), $user, $filters['organization'] ?? null)
            ->join('it_services as s', 's.id', '=', 'service_invoices.service_id')
            ->whereYear('service_invoices.period_start', $year)
            ->when($type !== null, fn ($q) => $q->where('s.type', $type?->value))
            ->when(! empty($filters['provider']), fn ($q) => $q->where('s.provider_id', (int) $filters['provider']))
            ->toBase()->selectRaw("{$key} AS group_key, MONTH(service_invoices.period_start) AS month")
            ->selectRaw('SUM(service_invoices.planned_amount) AS planned, COALESCE(SUM(service_invoices.amount), 0) AS invoiced, SUM(service_invoices.paid_amount) AS paid')
            ->groupBy('group_key', 'month')->get();

        $names = match ($groupBy) {
            'provider' => ServiceProvider::query()->whereIn('id', $raw->pluck('group_key'))->pluck('name', 'id'),
            'type' => collect(ServiceType::cases())->mapWithKeys(fn (ServiceType $t) => [$t->value => $t->label()]),
            default => Organization::query()->withoutGlobalScopes()->whereIn('id', $raw->pluck('group_key'))->pluck('name', 'id'),
        };
        $empty = fn () => ['planned' => '0.00', 'invoiced' => '0.00', 'paid' => '0.00'];
        $add = fn (array $a, array $b) => array_map(fn ($x, $y) => Decimal::add($x, $y, 2), $a, $b);
        $rows = [];
        foreach ($raw->groupBy('group_key') as $groupKey => $months) {
            $cells = array_fill(0, 12, $empty());
            foreach ($months as $m) {
                $cells[(int) $m->month - 1] = ['planned' => Decimal::of((string) $m->planned, 2), 'invoiced' => Decimal::of((string) $m->invoiced, 2), 'paid' => Decimal::of((string) $m->paid, 2)];
            }
            $total = array_combine(['planned', 'invoiced', 'paid'], array_reduce($cells, fn ($c, $cell) => $add($c, array_values($cell)), ['0.00', '0.00', '0.00']));
            $rows[] = ['key' => ['id' => $groupBy === 'type' ? (string) $groupKey : (int) $groupKey, 'name' => (string) $names->get($groupKey)], 'months' => $cells, 'total' => $total];
        }
        usort($rows, fn ($a, $b) => strcmp($a['key']['name'], $b['key']['name']));
        $grand = array_combine(['planned', 'invoiced', 'paid'], array_reduce($rows, fn ($c, $r) => $add($c, array_values($r['total'])), ['0.00', '0.00', '0.00']));

        return ['rows' => $rows, 'total' => $grand];
    }

    /** @return array<string, mixed> */
    public function simWidget(User $user, ?int $organizationId): array
    {
        $summary = $this->simSummary($user, ['organization' => $organizationId]);
        $byOperator = collect($summary['rows'])->groupBy('provider.id')->map(fn ($rows) => [
            'provider' => $rows->first()['provider'], 'count' => $rows->sum('total'),
        ])->sortByDesc('count')->values()->all();

        return [
            'total' => $summary['total']['total'],
            'by_status' => array_intersect_key($summary['total'], array_flip(['in_stock', 'active', 'blocked', 'returned'])),
            'by_operator' => $byOperator,
            'monthly_fee' => $summary['total']['monthly_fee'],
        ];
    }

    /** @return array<string, mixed> */
    public function servicesWidget(User $user, ?int $organizationId): array
    {
        $today = BillingService::today();
        $invoices = fn () => $this->scoped(ServiceInvoice::query(), $user, $organizationId);
        $month = $invoices()->whereBetween('due_date', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])
            ->toBase()->selectRaw('COALESCE(SUM(COALESCE(amount, planned_amount)), 0) AS planned, COALESCE(SUM(paid_amount), 0) AS paid')->first();
        $until = $today->addDays((int) $this->settings->get('services.due_notice_days'))->toDateString();

        return [
            'month_planned' => Decimal::of((string) ($month->planned ?? '0'), 2),
            'month_paid' => Decimal::of((string) ($month->paid ?? '0'), 2),
            'due_soon' => $invoices()->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Overdue->value])
                ->whereBetween('due_date', [$today->toDateString(), $until])->count(),
            'overdue' => $invoices()->where('status', InvoiceStatus::Overdue->value)->count(),
            'awaiting_approval' => $invoices()->whereNotNull('amount')->whereNull('approved_at')->where('status', '!=', InvoiceStatus::Paid->value)->count(),
            'contracts_expiring' => $this->scoped(ServiceContract::query(), $user, $organizationId)->whereNull('terminated_at')
                ->whereBetween('valid_to', [$today->toDateString(), $today->addDays((int) config('telecom.contract_notice_days'))->toDateString()])->count(),
        ];
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function scoped(Builder $query, User $user, mixed $organizationId): Builder
    {
        $scope = $user->accessScope();
        $column = $query->getModel()->getTable().'.organization_id';

        return $query->withoutGlobalScope(OrganizationScope::class)
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn($column, $scope->organizationIds() ?: [0]))
            ->when(! empty($organizationId), fn ($q) => $q->whereIn($column, OrganizationSubtree::ids((int) $organizationId) ?: [0]));
    }
}
