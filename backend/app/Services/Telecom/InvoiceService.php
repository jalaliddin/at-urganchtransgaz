<?php

namespace App\Services\Telecom;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Exceptions\DomainConflictException;
use App\Exceptions\InvalidTransitionException;
use App\Models\ItService;
use App\Models\ServiceInvoice;
use App\Models\ServicePayment;
use App\Models\User;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invoice entry, central approval and payments. Every change locks the invoice row; the paid total is read from
 * that locked row (a current read), so concurrent payments never exceed the invoiced amount.
 */
class InvoiceService
{
    public function __construct(private readonly BillingService $billing) {}

    /** @param  array<string, mixed>  $data  invoice_number, invoice_date, amount, due_date, note */
    public function enter(ServiceInvoice $invoice, array $data, User $by): ServiceInvoice
    {
        return $this->locked($invoice, function (ServiceInvoice $i) use ($data): void {
            $i->fill(array_intersect_key($data, array_flip(['invoice_number', 'invoice_date', 'due_date', 'note'])));
            if (array_key_exists('amount', $data)) {
                $amount = $data['amount'] === null ? null : Decimal::of($data['amount'], 2);
                $belowPaid = $amount === null ? Decimal::cmp($i->paid_amount, '0', 2) > 0 : Decimal::cmp($amount, $i->paid_amount, 2) < 0;
                if ($belowPaid) {
                    throw ValidationException::withMessages(['amount' => __('messages.invoice_below_paid', ['paid' => $i->paid_amount])]);
                }
                if ($amount !== $i->amount) {
                    // A different amount needs a new approval.
                    $i->forceFill(['amount' => $amount, 'approved_by' => null, 'approved_at' => null]);
                }
            }
        });
    }

    /**
     * One-off additional cost of a service (counted in the month of its invoice or due date).
     *
     * @param  array<string, mixed>  $data  description, amount, due_date, invoice_date, invoice_number, note
     */
    public function createExtra(ItService $service, array $data, User $by): ServiceInvoice
    {
        $tz = (string) config('app.display_timezone');
        $month = CarbonImmutable::parse((string) ($data['invoice_date'] ?? $data['due_date']), $tz)->startOfMonth();
        $amount = Decimal::of($data['amount'], 2);
        $due = CarbonImmutable::parse((string) $data['due_date'], $tz)->startOfDay();

        return ServiceInvoice::query()->create([
            'service_id' => $service->id, 'organization_id' => $service->organization_id, 'kind' => InvoiceKind::Extra,
            'period_start' => $month, 'period_end' => $month->endOfMonth()->startOfDay(), 'planned_amount' => $amount, 'amount' => $amount,
            'invoice_number' => $data['invoice_number'] ?? null, 'invoice_date' => $data['invoice_date'] ?? null, 'due_date' => $due,
            'description' => $data['description'], 'note' => $data['note'] ?? null,
            'status' => InvoiceStatus::derive($month, $due, $amount, '0', BillingService::today()),
        ]);
    }

    public function approve(ServiceInvoice $invoice, User $by): ServiceInvoice
    {
        return $this->locked($invoice, function (ServiceInvoice $i) use ($by): void {
            if ($i->amount === null) {
                throw ValidationException::withMessages(['amount' => __('messages.invoice_not_entered')]);
            }
            if ($i->approved_at !== null) {
                throw new InvalidTransitionException(__('messages.invoice_already_approved'));
            }
            $i->forceFill(['approved_by' => $by->id, 'approved_at' => CarbonImmutable::now()]);
        });
    }

    /** @param  array<string, mixed>  $data  amount, paid_at, document_number, note */
    public function pay(ServiceInvoice $invoice, array $data, User $by): ServicePayment
    {
        $payment = null;
        $this->locked($invoice, function (ServiceInvoice $i) use ($data, $by, &$payment): void {
            if ($i->amount === null || $i->approved_at === null) {
                throw ValidationException::withMessages(['amount' => __('messages.invoice_not_approved')]);
            }
            $amount = Decimal::of($data['amount'], 2);
            $remaining = Decimal::sub($i->amount, $i->paid_amount, 2);
            if (Decimal::cmp($amount, '0', 2) <= 0 || Decimal::cmp($amount, $remaining, 2) > 0) {
                throw ValidationException::withMessages(['amount' => __('messages.payment_exceeds', ['remaining' => $remaining])]);
            }
            $payment = ServicePayment::query()->create([
                'invoice_id' => $i->id, 'amount' => $amount, 'paid_at' => $data['paid_at'],
                'document_number' => $data['document_number'] ?? null, 'note' => $data['note'] ?? null, 'created_by' => $by->id,
            ]);
            $i->forceFill(['paid_amount' => Decimal::add($i->paid_amount, $amount, 2)]);
        });

        /** @var ServicePayment $payment */
        return $payment;
    }

    public function deletePayment(ServicePayment $payment): ServiceInvoice
    {
        $invoice = ServiceInvoice::query()->withoutGlobalScopes()->findOrFail($payment->invoice_id);

        return $this->locked($invoice, function (ServiceInvoice $i) use ($payment): void {
            $row = ServicePayment::query()->lockForUpdate()->findOrFail($payment->id);
            $row->delete();
            $i->forceFill(['paid_amount' => Decimal::sub($i->paid_amount, $row->amount, 2)]);
        });
    }

    /** Only an extra invoice without payments: regular periods are regenerated, so they stay (enter 0 instead). */
    public function delete(ServiceInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $i = ServiceInvoice::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
            if ($i->kind === InvoiceKind::Regular) {
                throw new DomainConflictException(__('messages.invoice_regular_kept'));
            }
            if ($i->payments()->exists()) {
                throw new DomainConflictException(__('messages.invoice_has_payments'));
            }
            $i->delete();
        });
    }

    /** @param  callable(ServiceInvoice): void  $work */
    private function locked(ServiceInvoice $invoice, callable $work): ServiceInvoice
    {
        return DB::transaction(function () use ($invoice, $work): ServiceInvoice {
            $locked = ServiceInvoice::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
            $work($locked);
            $locked->save();
            $this->billing->refresh($locked, BillingService::today());

            return $locked;
        }, 3);
    }
}
