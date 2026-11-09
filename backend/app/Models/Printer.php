<?php

namespace App\Models;

use App\Enums\PrinterConnection;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Printer-only data of an asset in the `printer` category (design P1). The row is
 * created on first write and doubles as the per-printer lock for counter and
 * replacement writes.
 *
 * @property int $asset_id
 * @property PrinterConnection|null $connection_type
 * @property bool $is_color
 * @property string|null $note
 * @property-read Asset $asset
 */
class Printer extends Model
{
    use HasUserStamps;

    protected $primaryKey = 'asset_id';

    public $incrementing = false;

    protected $fillable = ['asset_id', 'connection_type', 'is_color', 'note'];

    protected function casts(): array
    {
        return ['connection_type' => PrinterConnection::class, 'is_color' => 'boolean'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withoutGlobalScopes();
    }
}
