<?php

namespace App\Services\Printers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\CartridgeModel;
use App\Models\CartridgeReplacement;
use App\Models\Organization;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\Warehouse\StockQueries;
use App\Support\Decimal;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Consumption analysis (design P9) and the dashboard tile. Replacements and
 * readings count for the organization stored on them (design P8); cancelled
 * replacements never count.
 */
class PrinterReports
{
    public const GROUPS = ['organization', 'cartridge', 'printer'];

    private const KEYS = ['organization' => 'organization_id', 'cartridge' => 'cartridge_model_id', 'printer' => 'asset_id'];

    public function __construct(
        private readonly PrinterRegistry $registry,
        private readonly PrinterPages $pages,
        private readonly StockQueries $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  date_from, date_to (default: this year), group_by, organization
     * @return array{period: array{from: string, to: string}, group_by: string, rows: list<array<string, mixed>>, total: array<string, mixed>, monthly: list<array<string, mixed>>}
     */
    public function report(User $user, array $filters): array
    {
        [$from, $to] = $this->period($filters);
        $group = in_array($filters['group_by'] ?? null, self::GROUPS, true) ? (string) $filters['group_by'] : 'organization';
        $organization = empty($filters['organization']) ? null : (int) $filters['organization'];
        $column = self::KEYS[$group];

        $replaced = $this->replacements($user, $organization, $from, $to)
            ->groupBy("cartridge_replacements.{$column}")
            ->selectRaw("cartridge_replacements.{$column} AS k, COUNT(*) AS replacements, COALESCE(SUM(sd.total_amount), 0) AS cost")
            ->get()->keyBy('k');
        $pages = $group === 'cartridge' ? collect() : $this->readings($user, $organization, $from, $to)
            ->groupBy($column)->selectRaw("{$column} AS k, SUM(pages) AS pages")->pluck('pages', 'k');

        $ids = $replaced->keys()->merge($pages->keys())->map(fn ($id) => (int) $id)->unique()->values()->all();
        $names = $this->names($group, $ids);
        $rows = [];
        foreach ($ids as $id) {
            $line = $replaced->get($id);
            $rowPages = $group === 'cartridge' || ! isset($pages[$id]) ? null : (int) $pages[$id];
            $cost = Decimal::of((string) ($line->cost ?? '0'), 2);
            $rows[] = [
                'key' => ['id' => $id, ...($names[$id] ?? ['name' => '#'.$id, 'code' => null])],
                'replacements' => (int) ($line->replacements ?? 0),
                'cost' => $cost,
                'pages' => $rowPages,
                'cost_per_page' => $this->perPage($cost, $rowPages),
            ];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a['key']['name'], (string) $b['key']['name']));

        $monthly = $this->monthly($user, $organization, $from, $to);
        $totalCost = array_reduce($rows, fn (string $sum, array $r) => Decimal::add($sum, $r['cost'], 2), '0.00');
        $totalPages = array_sum(array_map(fn (array $m) => (int) ($m['pages'] ?? 0), $monthly));

        return [
            'period' => ['from' => $from, 'to' => $to],
            'group_by' => $group,
            'rows' => $rows,
            'total' => [
                'replacements' => array_sum(array_column($rows, 'replacements')),
                'cost' => $totalCost,
                'pages' => $totalPages,
                'cost_per_page' => $this->perPage($totalCost, $totalPages),
            ],
            'monthly' => $monthly,
        ];
    }

    /** @return array{printers: int, replacements_month: int, cartridges_in_stock: string, low_stock: int} */
    public function widget(User $user, ?int $organizationId): array
    {
        $today = CarbonImmutable::today((string) config('app.display_timezone'));
        $subtree = $organizationId === null ? null : (OrganizationSubtree::ids($organizationId) ?: [0]);
        $materials = CartridgeModel::query()->select('material_id');

        $stock = StockBalance::query()->visibleTo($user)->whereIn('material_id', $materials)
            ->when($subtree !== null, fn ($q) => $q->whereIn('warehouse_id', DB::table('warehouses')->select('id')->whereIn('organization_id', $subtree)))
            ->sum('quantity');
        $low = DB::query()->fromSub($this->stock->grouped($user, ['low' => true, 'organization' => $organizationId])
            ->whereIn('b.material_id', CartridgeModel::query()->select('material_id')), 'g')->count();

        return [
            'printers' => $this->registry->query($user)->where('assets.status', '!=', AssetStatus::WrittenOff->value)
                ->when($subtree !== null, fn ($q) => $q->whereIn('assets.organization_id', $subtree))->count(),
            'replacements_month' => CartridgeReplacement::query()->visibleTo($user)->active()
                ->whereBetween('replaced_at', [$today->startOfMonth()->toDateString(), $today->endOfMonth()->toDateString()])
                ->when($subtree !== null, fn ($q) => $q->whereIn('organization_id', $subtree))->count(),
            'cartridges_in_stock' => Decimal::of((string) ($stock ?: '0')),
            'low_stock' => $low,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    public function period(array $filters): array
    {
        $year = CarbonImmutable::today((string) config('app.display_timezone'))->year;

        return [
            empty($filters['date_from']) ? "{$year}-01-01" : (string) $filters['date_from'],
            empty($filters['date_to']) ? "{$year}-12-31" : (string) $filters['date_to'],
        ];
    }

    /** @return Builder<CartridgeReplacement> */
    private function replacements(User $user, ?int $organization, string $from, string $to): Builder
    {
        return CartridgeReplacement::query()->visibleTo($user)->active()
            ->leftJoin('stock_documents as sd', 'sd.id', '=', 'cartridge_replacements.stock_document_id')
            ->whereBetween('cartridge_replacements.replaced_at', [$from, $to])
            ->when($organization !== null, fn ($q) => $q->whereIn('cartridge_replacements.organization_id', OrganizationSubtree::ids($organization) ?: [0]));
    }

    private function readings(User $user, ?int $organization, string $from, string $to): QueryBuilder
    {
        $scope = $user->accessScope();

        return $this->pages->readings()->whereBetween('read_at', [$from, $to])
            ->when(! $scope->isUnrestricted(), fn ($q) => $q->whereIn('organization_id', $scope->organizationIds() ?: [0]))
            ->when($organization !== null, fn ($q) => $q->whereIn('organization_id', OrganizationSubtree::ids($organization) ?: [0]));
    }

    /** @return list<array{month: string, replacements: int, cost: string, pages: int|null}> */
    private function monthly(User $user, ?int $organization, string $from, string $to): array
    {
        $replaced = $this->replacements($user, $organization, $from, $to)
            ->groupByRaw("DATE_FORMAT(cartridge_replacements.replaced_at, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(cartridge_replacements.replaced_at, '%Y-%m') AS month, COUNT(*) AS replacements, COALESCE(SUM(sd.total_amount), 0) AS cost")
            ->get()->keyBy('month');
        $pages = $this->readings($user, $organization, $from, $to)
            ->groupByRaw("DATE_FORMAT(read_at, '%Y-%m')")->selectRaw("DATE_FORMAT(read_at, '%Y-%m') AS month, SUM(pages) AS pages")->pluck('pages', 'month');

        $rows = [];
        $last = CarbonImmutable::parse($to)->startOfMonth();
        for ($month = CarbonImmutable::parse($from)->startOfMonth(); $month <= $last; $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $line = $replaced->get($key);
            $rows[] = [
                'month' => $key,
                'replacements' => (int) ($line->replacements ?? 0),
                'cost' => Decimal::of((string) ($line->cost ?? '0'), 2),
                'pages' => isset($pages[$key]) ? (int) $pages[$key] : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{name: string, code: string|null}>
     */
    private function names(string $group, array $ids): array
    {
        return match ($group) {
            'organization' => Organization::query()->withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'name'])
                ->mapWithKeys(fn (Organization $o) => [$o->id => ['name' => $o->name, 'code' => null]])->all(),
            'cartridge' => CartridgeModel::query()->whereIn('id', $ids)->get(['id', 'name', 'part_number'])
                ->mapWithKeys(fn (CartridgeModel $c) => [$c->id => ['name' => $c->name, 'code' => $c->part_number]])->all(),
            default => Asset::query()->withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'name', 'inventory_number'])
                ->mapWithKeys(fn (Asset $a) => [$a->id => ['name' => $a->name, 'code' => $a->inventory_number]])->all(),
        };
    }

    private function perPage(string $cost, ?int $pages): ?string
    {
        return $pages !== null && $pages > 0 && Decimal::cmp($cost, '0', 2) > 0 ? Decimal::div($cost, (string) $pages, 2) : null;
    }
}
