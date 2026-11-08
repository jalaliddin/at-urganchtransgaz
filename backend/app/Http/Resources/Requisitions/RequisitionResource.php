<?php

namespace App\Http\Resources\Requisitions;

use App\Models\Requisition;
use App\Models\RequisitionApproval;
use App\Models\RequisitionAttachment;
use App\Models\RequisitionComment;
use App\Models\RequisitionFulfillment;
use App\Models\RequisitionHistory;
use App\Models\RequisitionItem;
use App\Models\User;

/** Plain arrays for requisitions (list row and full card). */
final class RequisitionResource
{
    public const ROW_RELATIONS = ['organization', 'requester'];

    public const DETAIL_RELATIONS = [
        'organization', 'requester', 'recipientEmployee', 'reviewer', 'approver',
        'items.material', 'items.unit', 'items.assetCategory',
        'approvals.user', 'histories.user', 'comments.user', 'attachments.file', 'attachments.uploader',
        'fulfillments.stockDocument', 'fulfillments.asset', 'fulfillments.assetTransfer', 'fulfillments.simCard', 'fulfillments.fulfiller',
    ];

    /** @return array<string, mixed> */
    public static function row(Requisition $r): array
    {
        return [
            'id' => $r->id, 'number' => $r->number, 'title' => $r->title, 'status' => $r->status->value,
            'organization' => ['id' => $r->organization->id, 'name' => $r->organization->name],
            'requester' => self::user($r->requester),
            'needed_by' => $r->needed_by?->toDateString(),
            'submitted_at' => $r->submitted_at?->toIso8601String(),
            'created_at' => $r->created_at->toIso8601String(),
            'updated_at' => $r->updated_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, bool>  $can
     * @return array<string, mixed>
     */
    public static function detail(Requisition $r, array $can): array
    {
        return [
            ...self::row($r),
            'description' => $r->description,
            'recipient_employee' => $r->recipientEmployee ? ['id' => $r->recipientEmployee->id, 'name' => $r->recipientEmployee->full_name] : null,
            'reviewer' => self::user($r->reviewer),
            'approved_by' => self::user($r->approver),
            'approved_at' => $r->approved_at?->toIso8601String(),
            'rejection_reason' => $r->rejection_reason,
            'received_at' => $r->received_at?->toIso8601String(),
            'closed_at' => $r->closed_at?->toIso8601String(),
            'items' => $r->items->map(fn (RequisitionItem $i) => [
                'id' => $i->id, 'kind' => $i->kind->value, 'name' => $i->name,
                'material' => $i->material ? ['id' => $i->material->id, 'code' => $i->material->code, 'name' => $i->material->name] : null,
                'asset_category' => $i->assetCategory ? ['id' => $i->assetCategory->id, 'name' => $i->assetCategory->localizedName()] : null,
                'quantity' => $i->quantity, 'approved_quantity' => $i->approved_quantity, 'fulfilled_quantity' => $i->fulfilled_quantity,
                'remaining' => $i->remaining(),
                'unit' => $i->unit ? ['id' => $i->unit->id, 'name' => $i->unit->localizedName()] : null,
                'note' => $i->note,
            ])->values(),
            'approvals' => $r->approvals->map(fn (RequisitionApproval $a) => [
                'id' => $a->id, 'stage' => $a->stage->value, 'decision' => $a->decision->value, 'comment' => $a->comment,
                'user' => self::user($a->user), 'created_at' => $a->created_at->toIso8601String(),
            ])->values(),
            'history' => $r->histories->map(fn (RequisitionHistory $h) => [
                'id' => $h->id, 'action' => $h->action->value, 'from_status' => $h->from_status, 'to_status' => $h->to_status,
                'comment' => $h->comment, 'payload' => $h->payload, 'user' => self::user($h->user),
                'created_at' => $h->created_at->toIso8601String(),
            ])->values(),
            'comments' => $r->comments->map(fn (RequisitionComment $c) => [
                'id' => $c->id, 'body' => $c->body, 'user' => self::user($c->user), 'created_at' => $c->created_at->toIso8601String(),
            ])->values(),
            'attachments' => $r->attachments->map(fn (RequisitionAttachment $a) => self::attachment($a))->values(),
            'fulfillments' => $r->fulfillments->map(fn (RequisitionFulfillment $f) => [
                'id' => $f->id, 'item_id' => $f->item_id, 'type' => $f->type->value, 'quantity' => $f->quantity, 'note' => $f->note,
                'stock_document' => $f->stockDocument ? ['id' => $f->stockDocument->id, 'number' => $f->stockDocument->number, 'type' => $f->stockDocument->type->value] : null,
                'asset' => $f->asset ? ['id' => $f->asset->id, 'inventory_number' => $f->asset->inventory_number, 'name' => $f->asset->name] : null,
                'asset_transfer' => $f->assetTransfer ? ['id' => $f->assetTransfer->id, 'number' => $f->assetTransfer->number, 'status' => $f->assetTransfer->status->value] : null,
                'sim_card' => $f->simCard ? ['id' => $f->simCard->id, 'phone_number' => $f->simCard->phone_number] : null,
                'user' => self::user($f->fulfiller), 'created_at' => $f->created_at->toIso8601String(),
            ])->values(),
            'can' => $can,
        ];
    }

    /** @return array<string, mixed> */
    public static function attachment(RequisitionAttachment $a): array
    {
        return [
            'id' => $a->id,
            'file' => ['uuid' => $a->file->uuid, 'name' => $a->file->original_name, 'mime' => $a->file->mime, 'size' => $a->file->size],
            'uploaded_by' => self::user($a->uploader), 'created_at' => $a->created_at->toIso8601String(),
        ];
    }

    /** @return array{id: int, name: string}|null */
    private static function user(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}
