<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Unit of measure for materials ("dona", "metr", ...).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $name_ru
 * @property bool $is_active
 * @property int $sort_order
 */
class Unit extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['code', 'name', 'name_ru', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function localizedName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru' && $this->name_ru ? $this->name_ru : $this->name;
    }
}
