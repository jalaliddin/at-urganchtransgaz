<?php

namespace App\Http\Resources\Telecom;

use App\Http\Resources\Warehouse\StockResource;
use App\Models\ServiceInvoice;
use App\Models\ServicePayment;

/** Plain arrays for invoices and payments. */
final class InvoiceResource
{
    public const RELATIONS = ['service.provider', 'organization', 'approver', 'file'];

    /** @return array<string, mixed> */
    public static function invoice(ServiceInvoice $i): array
    {
        return [
            'id' => $i->id, 'kind' => $i->kind->value, 'status' => $i->status->value,
            'service' => ['id' => $i->service->id, 'name' => $i->service->name, 'type' => $i->service->type->value,
                'provider' => ['id' => $i->service->provider->id, 'name' => $i->service->provider->name]],
            'organization' => ['id' => $i->organization->id, 'name' => $i->organization->name],
            'period_start' => $i->period_start->toDateString(), 'period_end' => $i->period_end->toDateString(),
            'planned_amount' => $i->planned_amount, 'invoice_number' => $i->invoice_number, 'invoice_date' => $i->invoice_date?->toDateString(),
            'amount' => $i->amount, 'due_date' => $i->due_date->toDateString(), 'paid_amount' => $i->paid_amount,
            'approved_by' => $i->approver ? ['id' => $i->approver->id, 'name' => $i->approver->name] : null,
            'approved_at' => $i->approved_at?->toIso8601String(),
            'file' => $i->file ? StockResource::file($i->file) : null,
            'description' => $i->description, 'note' => $i->note,
        ];
    }

    /** @return array<string, mixed> */
    public static function payment(ServicePayment $p): array
    {
        return [
            'id' => $p->id, 'amount' => $p->amount, 'paid_at' => $p->paid_at->toDateString(), 'document_number' => $p->document_number,
            'note' => $p->note, 'file' => $p->file ? StockResource::file($p->file) : null,
            'user' => $p->creator ? ['id' => $p->creator->id, 'name' => $p->creator->name] : null,
        ];
    }
}
