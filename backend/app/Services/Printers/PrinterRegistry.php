<?php

namespace App\Services\Printers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Printer;
use App\Models\User;
use App\Services\Assets\AssetService;
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
