<?php

namespace App\Http\Resources\Printers;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetModel;
use App\Models\CartridgeModel;
use App\Models\User;

/** JSON shapes of the printer module (plain arrays, like the other module resources). */
final class PrinterResource
{
    /** @return array<string, mixed> */
    public static function cartridge(CartridgeModel $cartridge, ?string $stock = null, ?bool $canDelete = null): array
    {
        return [
            'id' => $cartridge->id,
            'name' => $cartridge->name,
            'part_number' => $cartridge->part_number,
            'kind' => $cartridge->kind->value,
            'color' => $cartridge->color->value,
            'page_yield' => $cartridge->page_yield,
            'is_active' => $cartridge->is_active,
            'material' => $cartridge->relationLoaded('material') ? [
                'id' => $cartridge->material->id,
                'code' => $cartridge->material->code,
                'name' => $cartridge->material->name,
                'unit' => $cartridge->material->relationLoaded('unit') ? $cartridge->material->unit->name : null,
            ] : null,
            'printer_models' => $cartridge->relationLoaded('printerModels')
                ? $cartridge->printerModels->sortBy('name')->map(fn (AssetModel $m) => self::printerModel($m))->values()->all() : [],
            'stock' => $stock,
            'can_delete' => $canDelete,
        ];
    }

    /** @return array{id: int, name: string, manufacturer: string|null} */
    public static function printerModel(AssetModel $model): array
    {
        return ['id' => $model->id, 'name' => $model->name, 'manufacturer' => $model->relationLoaded('manufacturer') ? $model->manufacturer->name : null];
    }

    /**
     * Registry row: the asset fields of §5.12 plus the printer profile.
     *
     * @param  array{read_at: string, total: int}|null  $lastCounter
     * @param  array{replaced_at: string, cartridge: string}|null  $lastReplacement
     * @return array<string, mixed>
     */
    public static function printer(Asset $asset, ?array $lastCounter = null, ?array $lastReplacement = null): array
    {
        $profile = $asset->printer;

        return [
            'id' => $asset->id,
            'inventory_number' => $asset->inventory_number,
            'name' => $asset->name,
            'model' => $asset->model === null ? null : self::printerModel($asset->model),
            'serial_number' => $asset->serial_number,
            'ip_address' => $asset->ip_address,
            'hostname' => $asset->hostname,
            'status' => $asset->status->value,
            'organization' => ['id' => $asset->organization->id, 'name' => $asset->organization->name],
            'location' => $asset->location === null ? null : ['id' => $asset->location->id, 'name' => $asset->location->name],
            'room' => $asset->room,
            'holder' => $asset->holder === null ? null : ['id' => $asset->holder->id, 'full_name' => $asset->holder->full_name],
            'connection_type' => $profile?->connection_type?->value,
            'is_color' => (bool) $profile?->is_color,
            'note' => $profile?->note,
            'last_counter' => $lastCounter,
            'last_replacement' => $lastReplacement,
        ];
    }

    /** @return array{id: int, name: string}|null */
    public static function user(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }

    /** @return array<string, mixed> */
    public static function maintenance(AssetMaintenance $m): array
    {
        return [
            'id' => $m->id,
            'status' => $m->status->value,
            'started_at' => $m->started_at->toIso8601String(),
            'finished_at' => $m->finished_at?->toIso8601String(),
            'vendor' => $m->vendor,
            'description' => $m->description,
            'result' => $m->result,
            'cost' => $m->cost,
            'ticket' => $m->ticket === null ? null : ['id' => $m->ticket->id, 'number' => $m->ticket->number],
        ];
    }
}
