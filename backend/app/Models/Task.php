<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Card on a board. Visible to whoever sees the board; `organization_id` is the organization the work is for.
 *
 * @property int $id
 * @property string $number
 * @property int $board_id
 * @property int $column_id
 * @property int $position
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property string|null $project
 * @property int|null $assignee_id
 * @property TaskPriority $priority
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $completed_at
 * @property int|null $ticket_id
 * @property int|null $recurrence_id
 * @property CarbonImmutable|null $occurrence_date
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read TaskBoard $board
 * @property-read TaskColumn $column
 * @property-read Organization $organization
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read Ticket|null $ticket
 */
class Task extends Model
{
    use HasUserStamps;

    protected $fillable = [
        'number', 'board_id', 'column_id', 'position', 'organization_id', 'title', 'description', 'project', 'assignee_id',
        'priority', 'start_date', 'due_date', 'completed_at', 'ticket_id', 'recurrence_id', 'occurrence_date',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'position' => 'integer',
            'start_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
            'occurrence_date' => 'immutable_date',
        ];
    }

    /** @param  Builder<Task>  $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn($query->qualifyColumn('board_id'), TaskBoard::query()->visibleTo($user)->select('task_boards.id'));
    }

    /** Open (not in a done/cancelled column) with a due date before today. */
    public function isOverdue(?CarbonImmutable $today = null): bool
    {
        $today ??= CarbonImmutable::today((string) config('app.display_timezone'));

        return $this->completed_at === null && $this->due_date !== null && $this->due_date->lt($today);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();
        $query = static::query();
        if ($user instanceof User) {
            $query->visibleTo($user);
        }

        return $query->where($field ?? 'id', $value)->firstOrFail();
    }

    /** @return BelongsTo<TaskBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'board_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<TaskColumn, $this> */
    public function column(): BelongsTo
    {
        return $this->belongsTo(TaskColumn::class, 'column_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withoutGlobalScopes();
    }

    /** @return BelongsToMany<User, $this> */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')->withoutGlobalScopes();
    }

    /** @return BelongsToMany<TaskLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_label_task', 'task_id', 'label_id');
    }

    /** @return HasMany<TaskChecklistItem, $this> */
    public function checklist(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('id');
    }

    /** @return HasMany<TaskAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->orderBy('id');
    }

    /** @return HasMany<TaskHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(TaskHistory::class);
    }
}
