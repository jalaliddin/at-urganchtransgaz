<?php

namespace App\Models;

use App\Enums\AssetRelationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CMDB link: asset → related asset (e.g. PC connected_to switch, note "Gi0/14").
 *
 * @property int $id
 * @property int $asset_id
 * @property int $related_asset_id
 * @property AssetRelationType $type
 * @property string|null $note
 */
class AssetRelation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['asset_id', 'related_asset_id', 'type', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['type' => AssetRelationType::class];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<Asset, $this> */
    public function related(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'related_asset_id')->withoutGlobalScopes();
    }
}
