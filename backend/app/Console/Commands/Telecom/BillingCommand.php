<?php

namespace App\Console\Commands\Telecom;

use App\Enums\InvoiceStatus;
use App\Models\ServiceContract;
use App\Models\ServiceInvoice;
use App\Models\User;
use App\Notifications\Telecom\ContractExpiringNotification;
use App\Notifications\Telecom\ServicePaymentNotification;
use App\Services\Helpdesk\HelpdeskRecipients;
use App\Services\Settings\SettingsService;
use App\Services\Telecom\BillingService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Daily: billing periods for the current and next period, statuses by date, and reminders (once per object,
 * kind and date): payment due soon → the organization's own invoice/payment staff; overdue → them and the
 * central approvers; contract ending → service managers. No authenticated user: scope is explicit.
 */
class BillingCommand extends Command
{
    protected $signature = 'services:billing';

    protected $description = 'Generate service billing periods, refresh payment statuses and send reminders';

    public function handle(BillingService $billing, HelpdeskRecipients $recipients, SettingsService $settings): int
    {
        $today = BillingService::today();
        $created = $billing->generate($today);
        $changed = $billing->refreshOpen($today);

        $until = $today->addDays((int) $settings->get('services.due_notice_days'))->toDateString();
        $soon = ServiceInvoice::query()->withoutGlobalScopes()->with(['service.provider', 'organization'])
            ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Overdue->value])
            ->whereBetween('due_date', [$today->toDateString(), $until])->get();
        foreach ($soon as $invoice) {
            $this->remind($invoice, 'due_soon', $this->staff($recipients, $invoice->organization_id));
        }
        $overdue = ServiceInvoice::query()->withoutGlobalScopes()->with(['service.provider', 'organization'])
            ->where('status', InvoiceStatus::Overdue->value)->get();
        foreach ($overdue as $invoice) {
            $this->remind($invoice, 'overdue', $this->staff($recipients, $invoice->organization_id)
                ->merge($recipients->withPermission('services.approve', $invoice->organization_id))->unique('id')->values());
        }

        $contractsUntil = $today->addDays((int) config('telecom.contract_notice_days'))->toDateString();
        $contracts = ServiceContract::query()->withoutGlobalScopes()->with(['provider', 'organization'])
            ->whereNull('terminated_at')->whereBetween('valid_to', [$today->toDateString(), $contractsUntil])->get();
        foreach ($contracts as $contract) {
            if (! $this->once('contract_expiring', 'service_contract', $contract->id, (string) $contract->valid_to?->toDateString())) {
                continue;
            }
            Notification::send($recipients->withPermission('services.manage', $contract->organization_id), new ContractExpiringNotification(
                $contract->id, $contract->number, $contract->provider->name, $contract->organization->name, (string) $contract->valid_to?->format('d.m.Y'),
            ));
        }

        $this->info("periods: {$created}, statuses changed: {$changed}");

        return self::SUCCESS;
    }

    /**
     * Invoice/payment staff whose own organization is the invoice's; anyone with access when it has none.
     *
     * @return Collection<int, User>
     */
    private function staff(HelpdeskRecipients $recipients, int $organizationId): Collection
    {
        $all = $recipients->withPermission('services.pay', $organizationId)
            ->merge($recipients->withPermission('services.invoice', $organizationId))->unique('id')->values();
        $own = $all->filter(fn (User $u) => $u->organization_id === $organizationId)->values();

        return $own->isNotEmpty() ? $own : $all;
    }

    /** @param  Collection<int, User>  $users */
    private function remind(ServiceInvoice $invoice, string $event, Collection $users): void
    {
        if ($users->isEmpty() || ! $this->once($event, 'service_invoice', $invoice->id, $invoice->due_date->toDateString())) {
            return;
        }
        $amount = Decimal::sub($invoice->amount ?? $invoice->planned_amount, $invoice->paid_amount, 2);
        Notification::send($users, new ServicePaymentNotification(
            $event, $invoice->id, $invoice->service->name, $invoice->service->provider->name, $invoice->organization->name,
            number_format((float) $amount, 2, ',', ' '), $invoice->due_date->format('d.m.Y'),
        ));
    }

    private function once(string $type, string $alertableType, int $id, string $date): bool
    {
        return DB::table('service_alerts')->insertOrIgnore([
            'type' => $type, 'alertable_type' => $alertableType, 'alertable_id' => $id, 'alert_date' => $date, 'created_at' => CarbonImmutable::now(),
        ]) > 0;
    }
}
