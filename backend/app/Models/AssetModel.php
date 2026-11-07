<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device model, e.g. HP ProDesk 400 G7.
 *
 * @property int $id
 * @property int $manufacturer_id
 * @property int $category_id
 * @property string $name
 * @property string|null $specs
 * @property-read Manufacturer $manufacturer
 * @property-read AssetCategory $category
 */
class AssetModel extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['manufacturer_id', 'category_id', 'name', 'specs'];

    /** @return BelongsTo<Manufacturer, $this> */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }
}
