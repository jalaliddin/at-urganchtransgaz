<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property string|null $name_ru
 * @property bool $is_active
 * @property int $sort_order
 */
class MaterialCategory extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['code', 'name', 'name_ru', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<Material, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'category_id');
    }

    public function localizedName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru' && $this->name_ru ? $this->name_ru : $this->name;
    }
}
