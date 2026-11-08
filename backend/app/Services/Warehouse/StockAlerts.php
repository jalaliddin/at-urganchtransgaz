<?php

namespace App\Services\Warehouse;

use App\Enums\StockAlertType;
use App\Models\Material;
use App\Models\StockAlert;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\Warehouse\StockExpiringNotification;
use App\Notifications\Warehouse\StockLowNotification;
use App\Services\Helpdesk\HelpdeskRecipients;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Low-stock and expiry episodes (design W7). An alert row is opened once per
 * episode — open_key is unique while open — and only the opening notifies.
 */
class StockAlerts
{
    public function __construct(private readonly HelpdeskRecipients $recipients, private readonly StockQueries $queries) {}

    /** Re-evaluates the minimum of one material in one warehouse (after every posted document). */
    public function syncLowStock(int $warehouseId, int $materialId): void
    {
        $material = Material::query()->with('unit')->find($materialId);
        $warehouse = Warehouse::query()->withoutGlobalScopes()->find($warehouseId);
        if ($material === null || $warehouse === null) {
            return;
        }
        $total = Decimal::of((string) (StockBalance::query()->where('warehouse_id', $warehouseId)->where('material_id', $materialId)->sum('quantity') ?: '0'));
        $key = "low:{$warehouseId}:{$materialId}";
        $low = Decimal::cmp($material->min_quantity, '0') > 0 && Decimal::cmp($total, $material->min_quantity) < 0;

        if (! $low) {
            StockAlert::query()->withoutGlobalScopes()->where('open_key', $key)->update(['open_key' => null, 'resolved_at' => CarbonImmutable::now()]);

            return;
        }
        $opened = DB::table('stock_alerts')->insertOrIgnore([
            'organization_id' => $warehouse->organization_id, 'warehouse_id' => $warehouseId, 'material_id' => $materialId,
            'type' => StockAlertType::LowStock->value, 'batch' => '', 'quantity' => $total, 'open_key' => $key,
            'opened_at' => CarbonImmutable::now(),
        ]);
        if ($opened === 0) {
            StockAlert::query()->withoutGlobalScopes()->where('open_key', $key)->update(['quantity' => $total]);

            return;
        }
        Notification::send($this->recipients($warehouse), new StockLowNotification(
            $warehouse->name, $material->name, $total, $material->min_quantity, $material->unit->localizedName(), $warehouse->id, $material->id,
        ));
    }

    /**
     * Opens an alert for every lot with stock expiring within the notice period (once per
     * lot and expiry date), notifies per organization, and resolves alerts of emptied lots.
     *
     * @return int number of new alerts
     */
    public function syncExpiring(): int
    {
        $limit = $this->queries->expiryLimit();
        StockAlert::query()->withoutGlobalScopes()->where('type', StockAlertType::Expiring)->whereNotNull('open_key')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('stock_balances as b')
                ->whereColumn('b.warehouse_id', 'stock_alerts.warehouse_id')->whereColumn('b.material_id', 'stock_alerts.material_id')
                ->whereColumn('b.batch', 'stock_alerts.batch')->whereColumn('b.expires_at', 'stock_alerts.expires_at')->where('b.quantity', '>', 0))
            ->update(['open_key' => null, 'resolved_at' => CarbonImmutable::now()]);

        $lots = StockBalance::query()->with(['material', 'warehouse'])->where('quantity', '>', 0)
            ->whereNotNull('expires_at')->where('expires_at', '<=', $limit)->orderBy('expires_at')->get();

        /** @var array<int, Collection<int, StockBalance>> $fresh */
        $fresh = [];
        foreach ($lots as $lot) {
            $key = "exp:{$lot->id}:{$lot->expires_at?->toDateString()}";
            $known = StockAlert::query()->withoutGlobalScopes()->where('type', StockAlertType::Expiring)
                ->where('warehouse_id', $lot->warehouse_id)->where('material_id', $lot->material_id)
                ->where('batch', $lot->batch)->whereDate('expires_at', (string) $lot->expires_at?->toDateString())->exists();
            if ($known) {
                continue;
            }
            $inserted = DB::table('stock_alerts')->insertOrIgnore([
                'organization_id' => $lot->warehouse->organization_id, 'warehouse_id' => $lot->warehouse_id,
                'material_id' => $lot->material_id, 'type' => StockAlertType::Expiring->value, 'batch' => $lot->batch,
                'expires_at' => $lot->expires_at?->toDateString(), 'quantity' => $lot->quantity, 'open_key' => $key,
                'opened_at' => CarbonImmutable::now(),
            ]);
            if ($inserted > 0) {
                $fresh[$lot->warehouse->organization_id] ??= collect();
                $fresh[$lot->warehouse->organization_id]->push($lot);
            }
        }

        foreach ($fresh as $lotsOfOrg) {
            $first = $lotsOfOrg->first();
            if ($first === null) {
                continue;
            }
            Notification::send($this->recipients($first->warehouse), new StockExpiringNotification(
                $lotsOfOrg->map(fn (StockBalance $l) => sprintf('%s (%s) — %s, %s', $l->material->name, $l->batch, $l->warehouse->name, $l->expires_at?->format('d.m.Y')))->values()->all(),
                (int) config('warehouse.expiry_notice_days'),
            ));
        }

        return array_sum(array_map(fn (Collection $c) => $c->count(), $fresh));
    }

    /** @return Collection<int, User> holders of warehouse.receive in the warehouse's organization, plus its responsible user */
    private function recipients(Warehouse $warehouse): Collection
    {
        $users = $this->recipients->withPermission('warehouse.receive', $warehouse->organization_id);
        if ($warehouse->responsible_user_id !== null && ! $users->contains('id', $warehouse->responsible_user_id)) {
            $responsible = User::query()->withoutGlobalScopes()->where('is_active', true)->find($warehouse->responsible_user_id);
            if ($responsible !== null) {
                $users->push($responsible);
            }
        }

        return $users->values();
    }
}
