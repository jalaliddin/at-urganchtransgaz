<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Helpdesk ticket. Changes are recorded in ticket_histories (the ticket's own
 * audit trail) rather than audit_logs.
 *
 * @property int $id
 * @property string $number
 * @property int $organization_id
 * @property int|null $department_id
 * @property int|null $requester_employee_id
 * @property int|null $requester_user_id
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $location
 * @property int $category_id
 * @property string $title
 * @property string $description
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property int|null $assignee_id
 * @property CarbonImmutable|null $assigned_at
 * @property int $sla_response_minutes
 * @property int $sla_resolution_minutes
 * @property CarbonImmutable $response_due_at
 * @property CarbonImmutable $resolution_due_at
 * @property CarbonImmutable|null $first_response_at
 * @property CarbonImmutable|null $response_breached_at
 * @property CarbonImmutable|null $resolution_breached_at
 * @property CarbonImmutable|null $due_soon_notified_at
 * @property CarbonImmutable|null $paused_at
 * @property int $paused_minutes
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $rejected_at
 * @property string|null $resolution
 * @property string|null $reject_reason
 * @property int|null $rating
 * @property string|null $rating_comment
 * @property CarbonImmutable|null $rated_at
 * @property int $reopened_count
 * @property int $time_spent_minutes
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 */
class Ticket extends Model
{
    use BelongsToOrganization, HasUserStamps;

    protected $table = 'helpdesk_tickets';

    protected $fillable = [
        'number', 'organization_id', 'department_id', 'requester_employee_id', 'requester_user_id',
        'contact_name', 'contact_phone', 'contact_email', 'location', 'category_id', 'title', 'description',
        'priority', 'status', 'assignee_id', 'assigned_at', 'sla_response_minutes', 'sla_resolution_minutes',
        'response_due_at', 'resolution_due_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'assigned_at' => 'immutable_datetime',
            'response_due_at' => 'immutable_datetime',
            'resolution_due_at' => 'immutable_datetime',
            'first_response_at' => 'immutable_datetime',
            'response_breached_at' => 'immutable_datetime',
            'resolution_breached_at' => 'immutable_datetime',
            'due_soon_notified_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'rated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'sla_response_minutes' => 'integer',
            'sla_resolution_minutes' => 'integer',
            'paused_minutes' => 'integer',
            'reopened_count' => 'integer',
            'time_spent_minutes' => 'integer',
            'rating' => 'integer',
        ];
    }

    /**
     * Tickets $user may see: their own (as requester or creator) anywhere, plus —
     * with helpdesk.view — every ticket of the organizations in their scope.
     * Replaces the global organization scope, so it also works in queued jobs.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->withoutGlobalScope(OrganizationScope::class);
        $scope = $user->accessScope();
        $canView = $user->can('helpdesk.view');

        $query->where(function (Builder $q) use ($user, $scope, $canView): void {
            $q->where($q->qualifyColumn('requester_user_id'), $user->id)
                ->orWhere($q->qualifyColumn('created_by'), $user->id);
            if ($user->employee_id !== null) {
                $q->orWhere($q->qualifyColumn('requester_employee_id'), $user->employee_id);
            }
            if ($canView && $scope->isUnrestricted()) {
                $q->orWhereNotNull($q->qualifyColumn('id'));
            } elseif ($canView && $scope->organizationIds() !== []) {
                $q->orWhereIn($q->qualifyColumn('organization_id'), $scope->organizationIds());
            }
        });
    }

    /** Route binding honours visibility, so an invisible ticket is a plain 404. */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();
        $query = static::query();
        if ($user instanceof User) {
            $query->visibleTo($user);
        }

        return $query->where($field ?? $this->getRouteKeyName(), $value)->firstOrFail();
    }

    public function isRequester(User $user): bool
    {
        return $this->requester_user_id === $user->id
            || $this->created_by === $user->id
            || ($user->employee_id !== null && $this->requester_employee_id === $user->employee_id);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function requesterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }

    /** @return HasMany<TicketHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }
}
