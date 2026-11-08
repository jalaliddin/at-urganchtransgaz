<?php

namespace App\Http\Resources\Warehouse;

use App\Models\Material;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\StockMovement;
use App\Models\StoredFile;
use App\Models\Warehouse;

/** Plain arrays for stock documents, lots and journal rows. */
final class StockResource
{
    public const DOCUMENT_RELATIONS = [
        'organization', 'warehouse', 'targetWarehouse.organization', 'recipientOrganization', 'recipientEmployee',
        'asset', 'relatedDocument', 'file', 'creator', 'requisition',
    ];

    /** @return array<string, mixed> */
    public static function document(StockDocument $d): array
    {
        return [
            'id' => $d->id, 'number' => $d->number, 'type' => $d->type->value,
            'document_date' => $d->document_date->toDateString(),
            'organization' => $d->organization ? ['id' => $d->organization->id, 'name' => $d->organization->name] : null,
            'warehouse' => self::warehouseRef($d->warehouse),
            'target_warehouse' => $d->targetWarehouse ? [
                ...self::warehouseRef($d->targetWarehouse),
                'organization' => ['id' => $d->targetWarehouse->organization->id, 'name' => $d->targetWarehouse->organization->name],
            ] : null,
            'supplier' => $d->supplier, 'supplier_document' => $d->supplier_document,
            'recipient_organization' => $d->recipientOrganization ? ['id' => $d->recipientOrganization->id, 'name' => $d->recipientOrganization->name] : null,
            'recipient_employee' => $d->recipientEmployee ? ['id' => $d->recipientEmployee->id, 'name' => $d->recipientEmployee->full_name] : null,
            'asset' => $d->asset ? ['id' => $d->asset->id, 'inventory_number' => $d->asset->inventory_number, 'name' => $d->asset->name] : null,
            'reason' => $d->reason,
            'related_document' => $d->relatedDocument ? ['id' => $d->relatedDocument->id, 'number' => $d->relatedDocument->number, 'type' => $d->relatedDocument->type->value] : null,
            'requisition' => $d->requisition ? ['id' => $d->requisition->id, 'number' => $d->requisition->number] : null,
            'stock_count_id' => $d->stock_count_id,
            'file' => $d->file ? self::file($d->file) : null,
            'total_amount' => $d->total_amount,
            'created_by' => $d->creator ? ['id' => $d->creator->id, 'name' => $d->creator->name] : null,
            'created_at' => $d->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function documentDetail(StockDocument $d): array
    {
        $d->loadMissing(['lines.material.unit', 'movements.warehouse']);

        return [
            ...self::document($d),
            'lines' => $d->lines->map(fn ($l) => [
                'id' => $l->id, 'material' => self::materialRef($l->material), 'batch' => $l->batch,
                'expires_at' => $l->expires_at?->toDateString(), 'quantity' => $l->quantity, 'unit_cost' => $l->unit_cost,
                'amount' => $l->amount, 'note' => $l->note,
            ])->values(),
            'movements' => $d->movements->sortBy('id')->map(fn (StockMovement $m) => [
                'id' => $m->id, 'warehouse' => self::warehouseRef($m->warehouse), 'material_id' => $m->material_id,
                'batch' => $m->batch === '' ? null : $m->batch, 'quantity' => $m->quantity, 'unit_cost' => $m->unit_cost,
                'balance_after' => $m->balance_after,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    public static function movement(StockMovement $m): array
    {
        return [
            'id' => $m->id, 'created_at' => $m->created_at->toIso8601String(),
            'document' => ['id' => $m->document->id, 'number' => $m->document->number, 'type' => $m->document->type->value,
                'document_date' => $m->document->document_date->toDateString()],
            'warehouse' => self::warehouseRef($m->warehouse), 'material' => self::materialRef($m->material),
            'batch' => $m->batch === '' ? null : $m->batch, 'quantity' => $m->quantity, 'unit_cost' => $m->unit_cost,
            'balance_after' => $m->balance_after,
            'user' => $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name] : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function lot(StockBalance $b): array
    {
        return [
            'id' => $b->id, 'batch' => $b->batch === '' ? null : $b->batch, 'expires_at' => $b->expires_at?->toDateString(),
            'quantity' => $b->quantity, 'unit_cost' => $b->unit_cost, 'received_at' => $b->received_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function materialRef(Material $m): array
    {
        return ['id' => $m->id, 'code' => $m->code, 'name' => $m->name, 'unit' => $m->unit->localizedName(),
            'track_batches' => $m->track_batches, 'track_expiry' => $m->track_expiry];
    }

    /** @return array{id: int, name: string} */
    public static function warehouseRef(Warehouse $w): array
    {
        return ['id' => $w->id, 'name' => $w->name];
    }

    /** @return array<string, mixed> */
    public static function file(StoredFile $f): array
    {
        return ['uuid' => $f->uuid, 'name' => $f->original_name, 'mime' => $f->mime, 'size' => $f->size];
    }
}
