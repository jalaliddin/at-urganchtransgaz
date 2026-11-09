<?php

namespace App\Services\Telecom;

use App\Enums\BillingCycle;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\ItService;
use App\Models\ServiceInvoice;
use Carbon\CarbonImmutable;

/**
 * Billing periods of services. Periods follow the calendar (month, quarter, year); a regular invoice row is
 * kept for the current and the next period of every active service. Runs without an authenticated user, so
 * every query states its scope explicitly (all organizations).
 */
class BillingService
{
    /** @return int rows created */
    public function generate(CarbonImmutable $today): int
    {
        $created = 0;
        ItService::query()->withoutGlobalScopes()->where('is_active', true)->orderBy('id')
            ->chunkById(200, function ($services) use ($today, &$created): void {
                foreach ($services as $service) {
                    $created += $this->generateFor($service, $today);
                }
            });

        return $created;
    }

    /**
     * Current and next period of one service, when the service runs in it and the period is not already due
     * before tracking started (start date, or the day the service was registered).
     */
    public function generateFor(ItService $service, CarbonImmutable $today): int
    {
        if (! $service->is_active) {
            return 0;
        }
        $tz = (string) config('app.display_timezone');
        $tracking = ($service->started_at ?? CarbonImmutable::parse($service->created_at ?? $today, $tz))->startOfDay();
        [$start] = $this->periodOf($service->billing_cycle, $today);
        $created = 0;
        foreach ([$start, $start->addMonths($service->billing_cycle->months())] as $periodStart) {
            $periodEnd = $periodStart->addMonths($service->billing_cycle->months())->subDay();
            $due = $periodStart->setDay(min($service->due_day, $periodStart->daysInMonth));
            $runs = ($service->started_at === null || $service->started_at->lte($periodEnd))
                && ($service->ended_at === null || $service->ended_at->gte($periodStart));
            if (! $runs || $due->lt($tracking) || $this->overlapsOtherPeriod($service, $periodStart, $periodEnd)) {
                continue;
            }
            $invoice = ServiceInvoice::query()->withoutGlobalScopes()->firstOrCreate(
                ['period_key' => $service->id.':'.$periodStart->toDateString()],
                [
                    'service_id' => $service->id, 'organization_id' => $service->organization_id, 'kind' => InvoiceKind::Regular,
                    'period_start' => $periodStart, 'period_end' => $periodEnd, 'planned_amount' => $service->fee, 'due_date' => $due,
                    'status' => InvoiceStatus::derive($periodStart, $due, null, '0', $today),
                ],
            );
            $created += $invoice->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * After a service changed: open periods nobody has touched yet (no invoice, approval or payment) follow the
     * new fee and due day, or go away when the service no longer runs in them or the billing cycle changed;
     * then the current and next period are generated again. Entered invoices are the provider's figures and stay.
     */
    public function resync(ItService $service, CarbonImmutable $today): void
    {
        $untouched = ServiceInvoice::query()->withoutGlobalScopes()->where('service_id', $service->id)
            ->where('kind', InvoiceKind::Regular->value)->whereNull('amount')->whereNull('approved_at')->where('paid_amount', 0)
            ->where('period_end', '>=', $today->toDateString())->lockForUpdate()->get();
        foreach ($untouched as $invoice) {
            [$start, $end] = $this->periodOf($service->billing_cycle, $invoice->period_start);
            $runs = $service->is_active
                && ($service->started_at === null || $service->started_at->lte($invoice->period_end))
                && ($service->ended_at === null || $service->ended_at->gte($invoice->period_start));
            if (! $runs || ! $start->equalTo($invoice->period_start) || ! $end->equalTo($invoice->period_end)) {
                $invoice->delete();

                continue;
            }
            $invoice->forceFill([
                'planned_amount' => $service->fee,
                'due_date' => $start->setDay(min($service->due_day, $start->daysInMonth)),
            ])->save();
            $this->refresh($invoice, $today);
        }
        $this->generateFor($service, $today);
    }

    /** Another regular row of the service already covers part of this period (e.g. after a cycle change). */
    private function overlapsOtherPeriod(ItService $service, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return ServiceInvoice::query()->withoutGlobalScopes()->where('service_id', $service->id)
            ->where('kind', InvoiceKind::Regular->value)->where('period_key', '!=', $service->id.':'.$start->toDateString())
            ->where('period_start', '<=', $end->toDateString())->where('period_end', '>=', $start->toDateString())->exists();
    }

    /**
     * Calendar period containing $date: [first day, last day].
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function periodOf(BillingCycle $cycle, CarbonImmutable $date): array
    {
        $start = match ($cycle) {
            BillingCycle::Monthly => $date->startOfMonth(),
            BillingCycle::Quarterly => $date->firstOfQuarter(),
            BillingCycle::Yearly => $date->startOfYear(),
        };
        $start = $start->startOfDay();

        return [$start, $start->addMonths($cycle->months())->subDay()];
    }

    /** Recomputes and stores the status; true when it changed. */
    public function refresh(ServiceInvoice $invoice, CarbonImmutable $today): bool
    {
        $status = InvoiceStatus::derive($invoice->period_start, $invoice->due_date, $invoice->amount, $invoice->paid_amount, $today);
        if ($status === $invoice->status) {
            return false;
        }
        $invoice->forceFill(['status' => $status])->save();

        return true;
    }

    /** Daily: dates move planned → awaiting invoice → overdue. @return int rows changed */
    public function refreshOpen(CarbonImmutable $today): int
    {
        $changed = 0;
        ServiceInvoice::query()->withoutGlobalScopes()->where('status', '!=', InvoiceStatus::Paid->value)->orderBy('id')
            ->chunkById(500, function ($invoices) use ($today, &$changed): void {
                foreach ($invoices as $invoice) {
                    $changed += $this->refresh($invoice, $today) ? 1 : 0;
                }
            });

        return $changed;
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::today((string) config('app.display_timezone'));
    }
}
