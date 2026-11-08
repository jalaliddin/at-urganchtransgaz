<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Resource request of an organization (spec §5.10).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property string $title
 * @property string|null $description
 * @property RequisitionStatus $status
 * @property CarbonImmutable|null $needed_by
 * @property int|null $requester_id
 * @property int|null $recipient_employee_id
 * @property int|null $reviewer_id
 * @property CarbonImmutable|null $submitted_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $rejected_at
 * @property int|null $received_by
 * @property CarbonImmutable|null $received_at
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Organization $organization
 * @property-read User|null $requester
 * @property-read Collection<int, RequisitionItem> $items
 */
class Requisition extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'number', 'title', 'description', 'status', 'needed_by', 'requester_id', 'recipient_employee_id'];

    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'needed_by' => 'immutable_date',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Requisitions of $user's organizations: drafts only for their requester, the
     * rest for `requisitions.view` holders and the requester.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $scope = $user->accessScope();
        if (! $scope->isUnrestricted()) {
            $ids = $scope->organizationIds();
            if ($ids === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->whereIn($this->qualifyColumn('organization_id'), $ids);
        }
        $canView = $user->can('requisitions.view');
        $query->where(function (Builder $q) use ($user, $canView): void {
            $q->where($this->qualifyColumn('requester_id'), $user->id);
            if ($canView) {
                $q->orWhere($this->qualifyColumn('status'), '!=', RequisitionStatus::Draft->value);
            }
        });
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

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function recipientEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recipient_employee_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return HasMany<RequisitionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<RequisitionApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(RequisitionApproval::class)->orderBy('id');
    }

    /** @return HasMany<RequisitionHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(RequisitionHistory::class)->orderBy('id');
    }

    /** @return HasMany<RequisitionComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(RequisitionComment::class)->orderBy('id');
    }

    /** @return HasMany<RequisitionAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(RequisitionAttachment::class)->orderBy('id');
    }

    /** @return HasMany<RequisitionFulfillment, $this> */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(RequisitionFulfillment::class)->orderBy('id');
    }
}
