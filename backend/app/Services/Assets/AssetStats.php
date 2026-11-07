<?php

namespace App\Services\Assets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Asset register figures for reports and the dashboard (spec §5.1, §5.15). */
class AssetStats
{
    /**
     * @param  array<string, mixed>  $filters  AssetFilters names
     * @return array<string, mixed>
     */
    public function summary(User $user, array $filters = []): array
    {
        $base = fn (): Builder => $this->base($user, $filters);
        $active = fn (): Builder => $base()->where('assets.status', '!=', AssetStatus::WrittenOff->value);

        $counts = $base()->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $byStatus = [];
        foreach (AssetStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $byCategory = $active()->toBase()->join('asset_categories as c', 'c.id', '=', 'assets.category_id')
            ->select('c.id', 'c.code', 'c.name', 'c.name_ru', DB::raw('count(*) as total'))
            ->groupBy('c.id', 'c.code', 'c.name', 'c.name_ru')->orderByDesc('total')->orderBy('c.name')->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'code' => $r->code,
                'name' => app()->getLocale() === 'ru' && $r->name_ru ? (string) $r->name_ru : (string) $r->name,
                'total' => (int) $r->total,
            ])->all();

        $byOrganization = $active()->toBase()->join('organizations as o', 'o.id', '=', 'assets.organization_id')
            ->select('o.id', 'o.name', DB::raw('count(*) as total'),
                DB::raw("sum(case when assets.status = 'in_use' then 1 else 0 end) as in_use"),
                DB::raw("sum(case when assets.status = 'in_repair' then 1 else 0 end) as in_repair"))
            ->groupBy('o.id', 'o.name')->orderByDesc('total')->orderBy('o.name')->limit(50)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name, 'total' => (int) $r->total, 'in_use' => (int) $r->in_use, 'in_repair' => (int) $r->in_repair])
            ->all();

        $today = CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();
        $soon = CarbonImmutable::parse($today)->addDays((int) config('assets.warranty_notice_days'))->toDateString();

        return [
            'total' => array_sum($byStatus),
            'active' => array_sum($byStatus) - $byStatus[AssetStatus::WrittenOff->value],
            'by_status' => $byStatus,
            'by_category' => $byCategory,
            'by_organization' => $byOrganization,
            'total_cost' => number_format((float) $active()->sum('cost'), 2, '.', ''),
            'warranty_expiring' => $active()->whereBetween('warranty_until', [$today, $soon])->count(),
            'warranty_expired' => $active()->where('warranty_until', '<', $today)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Asset>
     */
    public function base(User $user, array $filters): Builder
    {
        $query = Asset::query()->visibleTo($user);
        AssetFilters::applyAll($query, $filters);

        return $query;
    }
}
