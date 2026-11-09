<?php

namespace App\Services\Assets;

use App\Enums\AssetEventAction;
use App\Enums\AssetStatus;
use App\Exceptions\DomainConflictException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetModel;
use App\Models\User;
use App\Services\Support\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Creating, editing and deleting assets. Status changes that have their own
 * process (assignment, repair, write-off, transfer) are not possible here.
 */
class AssetService
{
    /** Fields recorded in the history when edited. */
    private const TRACKED = [
        'name', 'category_id', 'manufacturer_id', 'model_id', 'department_id', 'serial_number', 'hostname',
        'ip_address', 'mac_address', 'vlan', 'subnet', 'responsible_employee_id', 'location_id', 'room',
        'purchase_date', 'warranty_until', 'cost', 'supplier', 'specs', 'notes', 'inventory_number',
    ];

    public function __construct(
        private readonly NumberSequence $numbers,
        private readonly AssetEventRecorder $events,
    ) {}

    /** @param  array<string, mixed>  $data  validated */
    public function create(array $data, User $by): Asset
    {
        return DB::transaction(function () use ($data, $by): Asset {
            $asset = new Asset($this->normalize($data));
            $asset->status = AssetStatus::from((string) ($data['status'] ?? AssetStatus::InStock->value));
            if (blank($asset->inventory_number)) {
                $asset->inventory_number = $this->nextInventoryNumber();
            }
            $asset->created_by = $by->id;
            $asset->save();

            $this->events->record($asset, $by, AssetEventAction::Created, new: [
                'inventory_number' => $asset->inventory_number, 'status' => $asset->status->value,
            ]);

            return $asset;
        });
    }

    /** @param  array<string, mixed>  $data  validated */
    public function update(Asset $asset, array $data, User $by): Asset
    {
        $this->assertEditable($asset);

        return DB::transaction(function () use ($asset, $data, $by): Asset {
            $asset->fill(array_intersect_key($this->normalize($data), array_flip(self::TRACKED)));
            if (! $asset->isDirty()) {
                return $asset;
            }
            $old = [];
            $new = [];
            foreach (array_keys($asset->getDirty()) as $key) {
                $old[$key] = $this->plain($asset->getOriginal($key));
                $new[$key] = $this->plain($asset->getAttribute($key));
            }
            $asset->save();
            $this->events->record($asset, $by, AssetEventAction::Updated, $old, $new);

            return $asset;
        });
    }

    /** Manual status: in stock, reserve or lost (and back). */
    public function setStatus(Asset $asset, AssetStatus $status, User $by, ?string $note): Asset
    {
        $this->assertEditable($asset);
        if ($asset->holder_employee_id !== null || $asset->status === AssetStatus::InRepair || $asset->status === AssetStatus::InUse) {
            throw new InvalidTransitionException(__('messages.asset_status_locked'));
        }
        if ($asset->status === $status) {
            return $asset;
        }

        return DB::transaction(function () use ($asset, $status, $by, $note): Asset {
            $old = $asset->status;
            $asset->status = $status;
            $asset->save();
            $this->events->record($asset, $by, AssetEventAction::StatusChanged, ['status' => $old->value], ['status' => $status->value], $note);

            return $asset;
        });
    }

    public function delete(Asset $asset): void
    {
        $operational = AssetAssignment::query()->where('asset_id', $asset->id)->exists()
            || DB::table('asset_maintenances')->where('asset_id', $asset->id)->exists()
            || DB::table('asset_disposals')->where('asset_id', $asset->id)->exists()
            || DB::table('asset_transfer_items')->where('asset_id', $asset->id)->exists()
            || DB::table('cartridge_replacements')->where('asset_id', $asset->id)->exists()
            || DB::table('printer_counters')->where('asset_id', $asset->id)->exists()
            || DB::table('change_assets')->where('asset_id', $asset->id)->exists()
            || DB::table('maintenance_tasks')->where('asset_id', $asset->id)->whereIn('status', ['done', 'skipped'])->exists();
        if ($operational) {
            throw new DomainConflictException(__('messages.asset_has_history'));
        }
        $asset->delete();
    }

    /** Written-off assets and assets on the way to another organization are read-only. */
    public function assertEditable(Asset $asset): void
    {
        if ($asset->status->isTerminal()) {
            throw new InvalidTransitionException(__('messages.asset_written_off'));
        }
        if ($asset->transfer_id !== null) {
            throw new InvalidTransitionException(__('messages.asset_in_transfer'));
        }
    }

    public function nextInventoryNumber(): string
    {
        do {
            $number = sprintf('AT-%06d', $this->numbers->next('asset'));
        } while (Asset::query()->withoutGlobalScopes()->where('inventory_number', $number)->exists());

        return $number;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (! empty($data['mac_address'])) {
            $data['mac_address'] = strtoupper(str_replace('-', ':', (string) $data['mac_address']));
        }
        if (! empty($data['model_id'])) {
            $data['manufacturer_id'] = AssetModel::query()->whereKey($data['model_id'])->value('manufacturer_id');
        }
        if (isset($data['inventory_number'])) {
            $data['inventory_number'] = trim((string) $data['inventory_number']);
        }

        return $data;
    }

    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    }
}
