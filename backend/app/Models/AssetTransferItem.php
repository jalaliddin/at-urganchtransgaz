<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transfer_id
 * @property int $asset_id
 * @property string|null $condition_note
 * @property-read Asset $asset
 */
class AssetTransferItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['transfer_id', 'asset_id', 'condition_note'];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }
}
