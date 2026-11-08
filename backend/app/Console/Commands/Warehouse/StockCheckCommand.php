<?php

namespace App\Console\Commands\Warehouse;

use App\Services\Warehouse\StockAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Daily: re-check minimum levels everywhere and raise expiry alerts. */
class StockCheckCommand extends Command
{
    protected $signature = 'warehouse:stock-check';

    protected $description = 'Re-check minimum stock levels and notify about lots expiring soon';

    public function handle(StockAlerts $alerts): int
    {
        DB::table('stock_balances as b')->join('materials as m', 'm.id', '=', 'b.material_id')
            ->where('m.min_quantity', '>', 0)->select('b.warehouse_id', 'b.material_id')->distinct()
            ->union(DB::table('stock_alerts')->whereNotNull('open_key')->where('type', 'low_stock')->select('warehouse_id', 'material_id'))
            ->get()->each(fn (object $pair) => $alerts->syncLowStock((int) $pair->warehouse_id, (int) $pair->material_id));

        $this->info('New expiry alerts: '.$alerts->syncExpiring());

        return self::SUCCESS;
    }
}
