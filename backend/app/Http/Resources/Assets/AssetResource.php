<?php

namespace App\Http\Resources\Assets;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Asset;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asset */
class AssetResource extends JsonResource
{
    use FormatsDates;

    public const RELATIONS = ['organization', 'department', 'category', 'manufacturer', 'model', 'holder', 'responsible', 'location.parent'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Asset $a */
        $a = $this->resource;
        /** @var User|null $user */
        $user = $request->user();
        $ref = fn ($model, string $label = 'name') => $model ? ['id' => $model->id, 'name' => $model->{$label}] : null;

        return [
            'id' => $a->id,
            'inventory_number' => $a->inventory_number,
            'name' => $a->name,
            'status' => $a->status->value,
            'category' => $a->category ? ['id' => $a->category->id, 'name' => $a->category->localizedName(), 'is_networked' => $a->category->is_networked] : null,
            'manufacturer' => $ref($a->manufacturer),
            'model' => $ref($a->model),
            'organization' => $ref($a->organization),
            'department' => $ref($a->department),
            'serial_number' => $a->serial_number,
            'hostname' => $a->hostname,
            'ip_address' => $a->ip_address,
            'mac_address' => $a->mac_address,
            'vlan' => $a->vlan,
            'subnet' => $a->subnet,
            'holder' => $a->holder ? ['id' => $a->holder->id, 'name' => $a->holder->full_name, 'status' => $a->holder->status->value] : null,
            'responsible' => $a->responsible ? ['id' => $a->responsible->id, 'name' => $a->responsible->full_name] : null,
            'location' => $a->location ? ['id' => $a->location->id, 'name' => $a->location->fullName()] : null,
            'room' => $a->room,
            'purchase_date' => $a->purchase_date?->format('Y-m-d'),
            'warranty_until' => $a->warranty_until?->format('Y-m-d'),
            'warranty_state' => $this->warrantyState($a),
            'cost' => $a->cost,
            'supplier' => $a->supplier,
            'specs' => $a->specs,
            'notes' => $a->notes,
            'in_transfer' => $a->transfer_id !== null,
            'created_at' => $this->iso($a->created_at),
            'updated_at' => $this->iso($a->updated_at),
            'can' => $user ? [
                'update' => $user->can('update', $a),
                'delete' => $user->can('delete', $a),
                'assign' => $user->can('assign', $a),
                'transfer' => $user->can('assets.transfer') && $user->canAccessOrganization($a->organization_id),
                'dispose' => $user->can('assets.dispose') && $user->canAccessOrganization($a->organization_id),
            ] : null,
        ];
    }

    private function warrantyState(Asset $a): ?string
    {
        if ($a->warranty_until === null) {
            return null;
        }
        $today = CarbonImmutable::now((string) config('app.display_timezone'))->startOfDay();
        if ($a->warranty_until->lessThan($today)) {
            return 'expired';
        }

        return $a->warranty_until->lessThanOrEqualTo($today->addDays((int) config('assets.warranty_notice_days'))) ? 'expiring' : 'ok';
    }
}
