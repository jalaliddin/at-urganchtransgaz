<?php

namespace App\Models;

use App\Enums\ColumnCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $board_id
 * @property string $name
 * @property ColumnCategory $category
 * @property int $position
 * @property int|null $wip_limit
 * @property-read TaskBoard $board
 */
class TaskColumn extends Model
{
    protected $fillable = ['board_id', 'name', 'category', 'position', 'wip_limit'];

    protected function casts(): array
    {
        return ['category' => ColumnCategory::class, 'position' => 'integer', 'wip_limit' => 'integer'];
    }

    /** @return BelongsTo<TaskBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id')->withoutGlobalScopes();
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'column_id')->orderBy('position');
    }
}
