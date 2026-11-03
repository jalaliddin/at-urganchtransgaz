<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Helpdesk ticket category (global reference data).
 *
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property string|null $name_ru
 * @property bool $is_active
 * @property int $sort_order
 */
class TicketCategory extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['code', 'name', 'name_ru', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function localizedName(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ru' && $this->name_ru ? $this->name_ru : $this->name;
    }
}
