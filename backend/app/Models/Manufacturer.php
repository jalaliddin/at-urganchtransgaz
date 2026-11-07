<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $website
 */
class Manufacturer extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['name', 'website'];

    /** @return HasMany<AssetModel, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(AssetModel::class);
    }
}
