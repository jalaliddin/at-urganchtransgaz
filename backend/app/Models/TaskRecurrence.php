<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Routine work that becomes a task on each occurrence date.
 *
 * @property int $id
 * @property int $board_id
 * @property int $column_id
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property int|null $assignee_id
 * @property TaskPriority $priority
 * @property list<int>|null $label_ids
 * @property RecurrenceFrequency $frequency
 * @property int $interval
 * @property int|null $weekday 1 = Monday … 7 = Sunday
 * @property int|null $month_day 1–28
 * @property int|null $due_in_days
 * @property CarbonImmutable $next_run_on
 * @property bool $is_active
 * @property-read TaskBoard $board
 * @property-read User|null $assignee
 */
class TaskRecurrence extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = [
        'board_id', 'column_id', 'organization_id', 'title', 'description', 'assignee_id', 'priority', 'label_ids',
        'frequency', 'interval', 'weekday', 'month_day', 'due_in_days', 'next_run_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'frequency' => RecurrenceFrequency::class,
            'label_ids' => 'array',
            'interval' => 'integer',
            'weekday' => 'integer',
            'month_day' => 'integer',
            'due_in_days' => 'integer',
            'next_run_on' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<TaskBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withoutGlobalScopes();
    }
}
