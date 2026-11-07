<?php

namespace App\Console\Commands\Assets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Organization;
use App\Notifications\Assets\AssetWarrantyExpiringNotification;
use App\Services\Helpdesk\HelpdeskRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Daily: tell asset managers about warranties ending soon (once per asset per warranty date). */
class WarrantyCheckCommand extends Command
{
    protected $signature = 'assets:warranty-check';

    protected $description = 'Notify asset managers about warranties ending soon';

    public function handle(HelpdeskRecipients $recipients): int
    {
        $days = (int) config('assets.warranty_notice_days');
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();
        $until = CarbonImmutable::parse($today)->addDays($days)->toDateString();

        $due = Asset::query()->withoutGlobalScopes()
            ->where('status', '!=', AssetStatus::WrittenOff->value)
            ->whereBetween('warranty_until', [$today, $until])
            ->where(fn ($q) => $q->whereNull('warranty_notified_for')->orWhereColumn('warranty_notified_for', '!=', 'warranty_until'))
            ->orderBy('inventory_number')
            ->get(['id', 'organization_id', 'inventory_number', 'warranty_until']);

        foreach ($due->groupBy('organization_id') as $organizationId => $assets) {
            $organization = Organization::query()->withoutGlobalScopes()->find($organizationId);
            DB::transaction(function () use ($assets): void {
                foreach ($assets as $asset) {
                    Asset::query()->withoutGlobalScopes()->whereKey($asset->id)->update(['warranty_notified_for' => $asset->warranty_until]);
                }
            });
            Notification::send(
                $recipients->withPermission('assets.assign', (int) $organizationId),
                new AssetWarrantyExpiringNotification((string) $organization?->name, $assets->pluck('inventory_number')->map(fn ($n) => (string) $n)->all(), $days),
            );
        }

        return self::SUCCESS;
    }
}
