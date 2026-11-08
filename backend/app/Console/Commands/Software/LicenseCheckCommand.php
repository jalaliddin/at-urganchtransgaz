<?php

namespace App\Console\Commands\Software;

use App\Models\SoftwareLicense;
use App\Notifications\Software\LicenseExpiringNotification;
use App\Services\Helpdesk\HelpdeskRecipients;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Daily: tell licence managers about licences ending within the notice period (once per licence and date). */
class LicenseCheckCommand extends Command
{
    protected $signature = 'software:license-check';

    protected $description = 'Notify licence managers about software licences ending soon';

    public function handle(HelpdeskRecipients $recipients): int
    {
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->toDateString();
        $until = CarbonImmutable::parse($today)->addDays((int) config('software.license_notice_days'))->toDateString();
        $licenses = SoftwareLicense::query()->withoutGlobalScopes()->with(['software', 'organization'])
            ->whereBetween('expires_at', [$today, $until])->get();

        foreach ($licenses as $license) {
            $date = (string) $license->expires_at?->toDateString();
            $fresh = DB::table('license_alerts')->insertOrIgnore(['license_id' => $license->id, 'expires_at' => $date, 'notified_at' => now()]);
            if ($fresh === 0) {
                continue;
            }
            Notification::send($recipients->withPermission('software.manage', $license->organization_id), new LicenseExpiringNotification(
                $license->id, $license->software->name, (string) $license->number, $license->organization->name, (string) $license->expires_at?->format('d.m.Y'),
            ));
        }

        return self::SUCCESS;
    }
}
