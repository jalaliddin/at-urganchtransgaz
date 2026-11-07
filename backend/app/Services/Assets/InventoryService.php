<?php

namespace App\Services\Assets;

use App\Enums\AssetEventAction;
use App\Enums\AssetStatus;
use App\Enums\InventoryStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Asset;
use App\Models\InventoryItem;
use App\Models\InventorySession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inventory count: freeze the expected list, mark scans, report found / missing /
 * unexpected. Never changes asset status — results are for people to act on.
 */
class InventoryService
{
    public function __construct(private readonly AssetEventRecorder $events) {}

    public function start(int $organizationId, bool $descendants, string $name, ?string $note, User $by): InventorySession
    {
        if (InventorySession::query()->where('organization_id', $organizationId)->where('status', InventoryStatus::Open)->exists()) {
            throw ValidationException::withMessages(['organization_id' => __('messages.inventory_open_exists')]);
        }

        return DB::transaction(function () use ($organizationId, $descendants, $name, $note, $by): InventorySession {
            $session = InventorySession::create([
                'organization_id' => $organizationId, 'include_descendants' => $descendants, 'name' => $name, 'note' => $note,
                'status' => InventoryStatus::Open, 'started_by' => $by->id, 'started_at' => CarbonImmutable::now(),
            ]);
            $assets = Asset::query()
                ->when($descendants, fn ($q) => $q->withinOrganizationSubtree($organizationId), fn ($q) => $q->where('organization_id', $organizationId))
                ->where('status', '!=', AssetStatus::WrittenOff->value)
                ->whereNull('transfer_id')
                ->get(['id', 'inventory_number']);
            $now = CarbonImmutable::now();
            foreach ($assets->chunk(500) as $chunk) {
                InventoryItem::query()->insert($chunk->map(fn (Asset $a) => [
                    'session_id' => $session->id, 'asset_id' => $a->id, 'inventory_number' => $a->inventory_number,
                    'expected' => true, 'created_at' => $now, 'updated_at' => $now,
                ])->all());
            }
            $session->forceFill(['expected_count' => $assets->count()])->save();

            return $session;
        });
    }

    /** @return array{result: string, item: InventoryItem} */
    public function scan(InventorySession $session, string $code, ?string $note, User $by): array
    {
        if ($session->status !== InventoryStatus::Open) {
            throw new InvalidTransitionException(__('messages.inventory_closed'));
        }
        $number = self::normalize($code);
        if ($number === '') {
            throw ValidationException::withMessages(['code' => __('validation.required', ['attribute' => 'code'])]);
        }

        return DB::transaction(function () use ($session, $number, $note, $by): array {
            $item = InventoryItem::query()->where('session_id', $session->id)->where('inventory_number', $number)->lockForUpdate()->first();
            if ($item !== null && $item->expected) {
                if ($item->found_at !== null) {
                    return ['result' => 'already_found', 'item' => $item];
                }
                $item->forceFill(['found_at' => CarbonImmutable::now(), 'scanned_by' => $by->id, 'note' => $note])->save();
                if ($item->asset !== null) {
                    $this->events->record($item->asset, $by, AssetEventAction::InventoryFound, new: ['session' => $session->name], note: $note);
                }

                return ['result' => 'found', 'item' => $item];
            }
            if ($item !== null) {
                return ['result' => 'already_scanned', 'item' => $item];
            }

            // Unknown here: link the asset only when the scanner may see it (no existence leak).
            $asset = Asset::query()->withoutGlobalScopes()->where('inventory_number', $number)->first();
            $visible = $asset !== null && $by->canAccessOrganization($asset->organization_id);
            $item = InventoryItem::create([
                'session_id' => $session->id, 'asset_id' => $visible ? $asset->id : null, 'inventory_number' => $number,
                'expected' => false, 'found_at' => CarbonImmutable::now(), 'scanned_by' => $by->id, 'note' => $note,
            ]);

            return ['result' => 'unexpected', 'item' => $item];
        });
    }

    public function close(InventorySession $session, ?string $note, User $by): InventorySession
    {
        if ($session->status !== InventoryStatus::Open) {
            throw new InvalidTransitionException(__('messages.inventory_closed'));
        }
        $session->forceFill([
            'status' => InventoryStatus::Closed, 'closed_by' => $by->id, 'closed_at' => CarbonImmutable::now(),
            'note' => $note ?? $session->note,
        ])->save();

        return $this->refreshCounts($session);
    }

    public function refreshCounts(InventorySession $session): InventorySession
    {
        $items = InventoryItem::query()->where('session_id', $session->id);
        $session->forceFill([
            'found_count' => (clone $items)->where('expected', true)->whereNotNull('found_at')->count(),
            'missing_count' => (clone $items)->where('expected', true)->whereNull('found_at')->count(),
            'unexpected_count' => (clone $items)->where('expected', false)->count(),
        ])->save();

        return $session;
    }

    /** Accepts a bare inventory number or a scanned label URL (…/assets/lookup/<number>). */
    public static function normalize(string $code): string
    {
        $code = trim($code);
        if (preg_match('#/assets/lookup/([^/?\#]+)#', $code, $m) === 1) {
            $code = rawurldecode($m[1]);
        }

        return mb_substr(trim($code), 0, 50);
    }
}
