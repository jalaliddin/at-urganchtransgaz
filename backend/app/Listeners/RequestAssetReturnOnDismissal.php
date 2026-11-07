<?php

namespace App\Listeners;

use App\Events\EmployeeDismissed;
use App\Models\Asset;
use App\Notifications\Assets\AssetsReturnRequiredNotification;
use App\Services\Helpdesk\HelpdeskRecipients;
use Illuminate\Support\Facades\Notification;

/** Spec §5.3: when an employee leaves, asset managers of the organization are asked to collect their assets. */
class RequestAssetReturnOnDismissal
{
    public function __construct(private readonly HelpdeskRecipients $recipients) {}

    public function handle(EmployeeDismissed $event): void
    {
        $numbers = Asset::query()->withoutGlobalScopes()->where('holder_employee_id', $event->employee->id)
            ->orderBy('inventory_number')->pluck('inventory_number')->map(fn ($n) => (string) $n)->all();
        if ($numbers === []) {
            return;
        }

        Notification::send(
            $this->recipients->withPermission('assets.assign', $event->employee->organization_id),
            new AssetsReturnRequiredNotification($event->employee, $numbers),
        );
    }
}
