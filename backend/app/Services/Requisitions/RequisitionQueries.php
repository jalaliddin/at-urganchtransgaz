<?php

namespace App\Services\Requisitions;

use App\Enums\RequisitionStatus as S;
use App\Models\Requisition;
use App\Models\RequisitionFulfillment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Shared requisition list conditions (screen tab, dashboard). */
final class RequisitionQueries
{
    /**
     * Requisitions waiting for something this user may do now (mirrors RequisitionWorkflow roles).
     *
     * @param  Builder<Requisition>  $query
     */
    public static function awaitingAction(Builder $query, User $user): void
    {
        $own = fn (Builder $q) => $q->where('requester_id', $user->id)
            ->when($user->can('requisitions.create') && $user->organization_id !== null, fn ($w) => $w->orWhere('organization_id', $user->organization_id));

        $query->where(function (Builder $q) use ($user, $own): void {
            $q->whereRaw('1 = 0');
            if ($user->can('requisitions.approve_org')) {
                $q->orWhere(fn ($w) => $w->where('status', S::OrgApproval)->where('requester_id', '!=', $user->id));
            }
            if ($user->can('requisitions.review')) {
                $q->orWhereIn('status', [S::Submitted->value, S::CentralReview->value]);
            } elseif ($user->can('requisitions.approve')) {
                $q->orWhere(fn ($w) => $w->whereIn('status', [S::Submitted->value, S::CentralReview->value])->where('requester_id', '!=', $user->id));
            }
            if ($user->can('requisitions.fulfill')) {
                $q->orWhereIn('status', [S::Approved->value, S::ReadyForAllocation->value, S::PartiallyFulfilled->value]);
            }
            if ($user->canAny(['requisitions.approve', 'requisitions.fulfill'])) {
                $q->orWhere('status', S::Received);
            }
            $q->orWhere(fn ($w) => $w->whereIn('status', [S::InfoRequested->value, S::Fulfilled->value])->where($own));
        });
    }

    /**
     * Whether an asset or SIM card was already given to a requisition that is not received or closed yet.
     * A locking (current) read: called under the object's row lock, it must see allocations committed while
     * this transaction waited, not its REPEATABLE READ snapshot.
     *
     * @param  'asset_id'|'sim_card_id'  $column
     */
    public static function promised(string $column, int $id): bool
    {
        return RequisitionFulfillment::query()->where($column, $id)
            ->whereIn('requisition_id', Requisition::query()->withoutGlobalScopes()->select('id')->whereNotIn('status', [S::Received->value, S::Closed->value]))
            ->lockForUpdate()->value('id') !== null;
    }
}
