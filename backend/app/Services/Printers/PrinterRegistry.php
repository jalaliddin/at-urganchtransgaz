<?php

namespace App\Services\Printers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Printer;
use App\Models\User;
use App\Services\Assets\AssetService;
use App\Support\OrganizationSubtree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The printer registry: assets of the `printer` category in the user's scope
 * (design P1). Printer writes lock the asset row — it always exists, unlike the
 * profile row, which is created on first write.
 */
class PrinterRegistry
{
    public function __construct(private readonly AssetService $assets) {}

    /** @return Builder<Asset> */
    public function query(User $user): Builder
    {
        return Asset::query()->visibleTo($user)
            ->whereIn('assets.category_id', AssetCategory::query()->select('id')->where('code', AssetCategory::PRINTER));
    }

    public const FILTERS = ['organization', 'connection', 'model', 'status', 'cartridge', 'search'];

    /**
     * Registry list with the filters of the printers page and its export.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Asset>
     */
    public function filtered(User $user, array $filters): Builder
    {
        $query = $this->query($user);
        if (! empty($filters['organization'])) {
            $query->whereIn('assets.organization_id', OrganizationSubtree::ids((int) $filters['organization']) ?: [0]);
        }
        if (! empty($filters['connection'])) {
            $query->whereHas('printer', fn ($q) => $q->whereIn('connection_type', explode(',', (string) $filters['connection'])));
        }
        if (! empty($filters['model'])) {
            $query->where('assets.model_id', (int) $filters['model']);
        }
        if (! empty($filters['status'])) {
            $query->whereIn('assets.status', explode(',', (string) $filters['status']));
        }
        if (! empty($filters['cartridge'])) {
            $query->whereIn('assets.model_id', DB::table('printer_cartridge_compatibilities')->select('asset_model_id')
                ->where('cartridge_model_id', (int) $filters['cartridge']));
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('assets.inventory_number', 'like', "%{$search}%")->orWhere('assets.name', 'like', "%{$search}%")
                ->orWhere('assets.serial_number', 'like', "%{$search}%")->orWhere('assets.ip_address', 'like', "%{$search}%")
                ->orWhere('assets.hostname', 'like', "%{$search}%"));
        }

        return $query->orderBy('assets.inventory_number');
    }

    /**
     * Latest counter reading per printer.
     *
     * @param  list<int>  $ids
     * @return array<int, array{read_at: string, total: int}>
     */
    public function lastCounters(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $ranked = DB::table('printer_counters')->whereIn('asset_id', $ids)
            ->selectRaw('asset_id, read_at, total, ROW_NUMBER() OVER (PARTITION BY asset_id ORDER BY read_at DESC, id DESC) AS rn');

        return DB::query()->fromSub($ranked, 'c')->where('rn', 1)->get()
            ->mapWithKeys(fn ($r) => [(int) $r->asset_id => ['read_at' => (string) $r->read_at, 'total' => (int) $r->total]])->all();
    }

    /**
     * Latest active replacement per printer.
     *
     * @param  list<int>  $ids
     * @return array<int, array{replaced_at: string, cartridge: string}>
     */
    public function lastReplacements(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $ranked = DB::table('cartridge_replacements')->join('cartridge_models', 'cartridge_models.id', '=', 'cartridge_replacements.cartridge_model_id')
            ->whereIn('cartridge_replacements.asset_id', $ids)->whereNull('cartridge_replacements.cancelled_at')
            ->selectRaw('cartridge_replacements.asset_id, cartridge_replacements.replaced_at, cartridge_models.name AS cartridge,
                ROW_NUMBER() OVER (PARTITION BY cartridge_replacements.asset_id ORDER BY cartridge_replacements.replaced_at DESC, cartridge_replacements.id DESC) AS rn');

        return DB::query()->fromSub($ranked, 'r')->where('rn', 1)->get()
            ->mapWithKeys(fn ($r) => [(int) $r->asset_id => ['replaced_at' => (string) $r->replaced_at, 'cartridge' => (string) $r->cartridge]])->all();
    }

    /** A printer in the user's scope, or 404. */
    public function find(User $user, int $id): Asset
    {
        return $this->query($user)->whereKey($id)->firstOrFail();
    }

    /** Locks and returns a fresh copy of the printer's asset row. Call inside a transaction. */
    public function lock(Asset $printer): Asset
    {
        return Asset::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($printer->id);
    }

    /** Locks the printer and checks it may still change (written off / in transfer → 422). */
    public function lockEditable(Asset $printer): Asset
    {
        $locked = $this->lock($printer);
        $this->assets->assertEditable($locked);

        return $locked;
    }

    /** @param  array<string, mixed>  $data  connection_type, is_color, note */
    public function updateProfile(Asset $printer, array $data): Printer
    {
        return DB::transaction(function () use ($printer, $data): Printer {
            $this->lockEditable($printer);
            $profile = Printer::query()->find($printer->id) ?? new Printer(['asset_id' => $printer->id]);
            $profile->fill($data)->save();

            return $profile;
        });
    }
}
