<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $task_id
 * @property string $title
 * @property bool $is_done
 * @property int $position
 * @property int|null $done_by
 * @property CarbonImmutable|null $done_at
 */
class TaskChecklistItem extends Model
{
    protected $fillable = ['task_id', 'title', 'is_done', 'position', 'done_by', 'done_at'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'position' => 'integer', 'done_at' => 'immutable_datetime'];
    }
}
