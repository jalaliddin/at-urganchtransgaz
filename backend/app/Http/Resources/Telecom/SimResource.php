<?php

namespace App\Http\Resources\Telecom;

use App\Models\ServiceProvider;
use App\Models\SimAssignment;
use App\Models\SimCard;
use App\Models\SimHistory;

/** Plain arrays for SIM data. The ICCID is only ever masked here. */
final class SimResource
{
    /** @return array<string, mixed> */
    public static function provider(ServiceProvider $p): array
    {
        return [
            'id' => $p->id, 'name' => $p->name, 'tin' => $p->tin, 'phone' => $p->phone, 'email' => $p->email,
            'address' => $p->address, 'is_mobile_operator' => $p->is_mobile_operator, 'is_active' => $p->is_active, 'note' => $p->note,
        ];
    }

    /** @return array<string, mixed> */
    public static function card(SimCard $c): array
    {
        return [
            'id' => $c->id, 'phone_number' => $c->phone_number,
            'provider' => ['id' => $c->provider->id, 'name' => $c->provider->name],
            'organization' => ['id' => $c->organization->id, 'name' => $c->organization->name],
            'department' => $c->department ? ['id' => $c->department->id, 'name' => $c->department->name] : null,
            'employee' => $c->employee ? ['id' => $c->employee->id, 'full_name' => $c->employee->full_name] : null,
            'status' => $c->status->value, 'previous_status' => $c->previous_status?->value,
            'tariff' => $c->tariff, 'account_number' => $c->account_number, 'monthly_fee' => $c->monthly_fee,
            'iccid_masked' => $c->maskedIccid(), 'has_iccid' => $c->iccid_last4 !== null,
            'allocated_at' => $c->allocated_at?->toDateString(), 'returned_at' => $c->returned_at?->toDateString(),
            'note' => $c->note,
        ];
    }

    /** @return array<string, mixed> */
    public static function assignment(SimAssignment $a): array
    {
        return [
            'id' => $a->id,
            'employee' => $a->employee ? ['id' => $a->employee->id, 'full_name' => $a->employee->full_name] : null,
            'department' => $a->department ? ['id' => $a->department->id, 'name' => $a->department->name] : null,
            'assigned_at' => $a->assigned_at->toDateString(), 'returned_at' => $a->returned_at?->toDateString(), 'note' => $a->note,
        ];
    }

    /** @return array<string, mixed> */
    public static function history(SimHistory $h): array
    {
        return [
            'id' => $h->id, 'event' => $h->event->value, 'payload' => $h->payload ?? (object) [],
            'user' => $h->user ? ['id' => $h->user->id, 'name' => $h->user->name] : null,
            'requisition_id' => $h->requisition_id, 'created_at' => $h->created_at?->toIso8601String(),
        ];
    }
}
