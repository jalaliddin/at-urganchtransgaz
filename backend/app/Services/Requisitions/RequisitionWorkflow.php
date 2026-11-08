<?php

namespace App\Services\Requisitions;

use App\Enums\RequisitionAction;
use App\Enums\RequisitionDecision;
use App\Enums\RequisitionStage;
use App\Enums\RequisitionStatus as S;
use App\Exceptions\InvalidTransitionException;
use App\Models\Requisition;
use App\Models\RequisitionApproval;
use App\Models\RequisitionHistory;
use App\Models\RequisitionItem;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Requisition state machine (design R2). Each action has the statuses it starts
 * from and the role that may perform it; role failures are 403, status failures
 * 422 invalid_transition (checked on the row re-read under lock).
 */
class RequisitionWorkflow
{
    /** @var array<string, list<S>> */
    private const FROM = [
        'edit' => [S::Draft],
        'delete' => [S::Draft],
        'submit' => [S::Draft],
        'org_decide' => [S::OrgApproval],
        'take' => [S::Submitted],
        'request_info' => [S::CentralReview],
        'provide_info' => [S::InfoRequested],
        'decide' => [S::Submitted, S::CentralReview],
        'ready' => [S::Approved],
        'allocate' => [S::Approved, S::ReadyForAllocation, S::PartiallyFulfilled],
        'complete' => [S::PartiallyFulfilled],
        'receive' => [S::Fulfilled],
        'close' => [S::Received],
        'comment' => [S::Draft, S::Submitted, S::OrgApproval, S::CentralReview, S::InfoRequested, S::Approved,
            S::ReadyForAllocation, S::PartiallyFulfilled, S::Fulfilled, S::Received],
    ];

    public const ACTIONS = ['edit', 'delete', 'submit', 'org_decide', 'take', 'request_info', 'provide_info', 'decide', 'ready', 'allocate', 'complete', 'receive', 'close', 'comment'];

    public function __construct(private readonly RequisitionNotifier $notifier, private readonly SettingsService $settings) {}

    /** The user's role allows $action on this requisition (status not considered). */
    public function roleAllows(string $action, Requisition $requisition, User $user): bool
    {
        $requester = $requisition->requester_id === $user->id;
        // The receiving side: the requester, or an IT user of the requesting organization itself.
        $ownSide = $requester || ($user->can('requisitions.create') && $user->organization_id === $requisition->organization_id);

        return match ($action) {
            'edit', 'delete', 'submit' => $requester,
            'provide_info', 'receive' => $ownSide,
            'org_decide' => ! $requester && $user->can('requisitions.approve_org') && $user->canAccessOrganization($requisition->organization_id),
            'take', 'request_info' => $user->can('requisitions.review'),
            'decide' => ! $requester && $user->can('requisitions.approve'),
            'ready', 'allocate', 'complete' => $user->can('requisitions.fulfill'),
            'close' => $user->can('requisitions.approve') || $user->can('requisitions.fulfill'),
            'comment' => $requester || $user->can('requisitions.view'),
            default => false,
        };
    }

    public function can(string $action, Requisition $requisition, User $user): bool
    {
        return in_array($requisition->status, self::FROM[$action] ?? [], true) && $this->roleAllows($action, $requisition, $user);
    }

    /** @return array<string, bool> */
    public function abilities(Requisition $requisition, User $user): array
    {
        $can = [];
        foreach (self::ACTIONS as $action) {
            $can[$action] = $this->can($action, $requisition, $user);
        }

        return $can;
    }

    /**
     * Role check, then the row is re-read with a lock and its status checked there;
     * the caller's model is refreshed with the locked state.
     */
    public function lockFor(string $action, Requisition $requisition, User $user): void
    {
        if (! $this->roleAllows($action, $requisition, $user)) {
            throw new AuthorizationException;
        }
        $locked = Requisition::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($requisition->id);
        if (! in_array($locked->status, self::FROM[$action] ?? [], true)) {
            throw new InvalidTransitionException(__('messages.invalid_transition'));
        }
        $requisition->setRawAttributes($locked->getAttributes(), true);
    }

    /** @param  array<string, mixed>|null  $payload */
    public function record(Requisition $requisition, RequisitionAction $action, User $by, ?S $from = null, ?S $to = null, ?string $comment = null, ?array $payload = null): void
    {
        RequisitionHistory::create([
            'requisition_id' => $requisition->id, 'user_id' => $by->id, 'action' => $action,
            'from_status' => $from?->value, 'to_status' => $to?->value, 'comment' => $comment, 'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $payload
     */
    public function move(Requisition $requisition, S $to, RequisitionAction $action, User $by, ?string $comment = null, array $attributes = [], ?array $payload = null): void
    {
        $from = $requisition->status;
        $requisition->forceFill([...$attributes, 'status' => $to])->save();
        $this->record($requisition, $action, $by, $from, $to, $comment, $payload);
    }

    public function submit(Requisition $requisition, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $by): void {
            $this->lockFor('submit', $requisition, $by);
            if (! $requisition->items()->exists()) {
                throw ValidationException::withMessages(['items' => __('messages.requisition_items_required')]);
            }
            $headStage = (bool) $this->settings->get('requisitions.head_approval');
            $approvers = $headStage ? $this->notifier->approvers($requisition) : collect();
            $to = $approvers->isNotEmpty() ? S::OrgApproval : S::Submitted;
            $this->move($requisition, $to, RequisitionAction::Submitted, $by, attributes: ['submitted_at' => CarbonImmutable::now()]);
            if ($headStage && $approvers->isEmpty()) {
                $this->record($requisition, RequisitionAction::ApprovalSkipped, $by);
            }
            $to === S::OrgApproval
                ? $this->notifier->send($requisition, 'org_approval', $approvers, $by)
                : $this->notifier->send($requisition, 'queued', $this->notifier->holders('requisitions.review', $requisition), $by);
        });

        return $requisition;
    }

    public function orgDecide(Requisition $requisition, RequisitionDecision $decision, ?string $comment, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $decision, $comment, $by): void {
            $this->lockFor('org_decide', $requisition, $by);
            $this->requireComment($decision, $comment);
            RequisitionApproval::create(['requisition_id' => $requisition->id, 'stage' => RequisitionStage::OrgHead,
                'decision' => $decision, 'user_id' => $by->id, 'comment' => $comment]);
            match ($decision) {
                RequisitionDecision::Approved => $this->move($requisition, S::Submitted, RequisitionAction::OrgApproved, $by, $comment),
                RequisitionDecision::Rejected => $this->move($requisition, S::Rejected, RequisitionAction::OrgRejected, $by, $comment,
                    ['rejection_reason' => $comment, 'rejected_at' => CarbonImmutable::now()]),
                RequisitionDecision::Returned => $this->move($requisition, S::Draft, RequisitionAction::Returned, $by, $comment),
            };
            $decision === RequisitionDecision::Approved
                ? $this->notifier->send($requisition, 'queued', $this->notifier->holders('requisitions.review', $requisition), $by)
                : $this->notifier->send($requisition, $decision->value, $this->notifier->requester($requisition), $by, $comment);
        });

        return $requisition;
    }

    public function take(Requisition $requisition, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $by): void {
            $this->lockFor('take', $requisition, $by);
            $this->move($requisition, S::CentralReview, RequisitionAction::Taken, $by, attributes: ['reviewer_id' => $by->id]);
        });

        return $requisition;
    }

    public function requestInfo(Requisition $requisition, ?string $comment, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $comment, $by): void {
            $this->lockFor('request_info', $requisition, $by);
            $this->requireComment(null, $comment);
            $this->move($requisition, S::InfoRequested, RequisitionAction::InfoRequested, $by, $comment);
            $this->notifier->send($requisition, 'info_requested', $this->notifier->requester($requisition), $by, $comment);
        });

        return $requisition;
    }

    public function provideInfo(Requisition $requisition, ?string $comment, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $comment, $by): void {
            $this->lockFor('provide_info', $requisition, $by);
            $this->requireComment(null, $comment);
            $this->move($requisition, S::CentralReview, RequisitionAction::InfoProvided, $by, $comment);
            $reviewer = $requisition->reviewer_id === null ? collect() : User::query()->withoutGlobalScopes()->whereKey($requisition->reviewer_id)->get();
            $this->notifier->send($requisition, 'info_provided', $reviewer->isNotEmpty() ? $reviewer : $this->notifier->holders('requisitions.review', $requisition), $by, $comment);
        });

        return $requisition;
    }

    /**
     * Central head decision. Approval may lower item quantities (never raise them);
     * at least one item must stay above zero.
     *
     * @param  list<array{id: int, approved_quantity: string|int|float}>  $quantities
     */
    public function decide(Requisition $requisition, RequisitionDecision $decision, ?string $comment, array $quantities, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $decision, $comment, $quantities, $by): void {
            $this->lockFor('decide', $requisition, $by);
            $this->requireComment($decision, $comment);
            RequisitionApproval::create(['requisition_id' => $requisition->id, 'stage' => RequisitionStage::CentralHead,
                'decision' => $decision, 'user_id' => $by->id, 'comment' => $comment]);

            if ($decision === RequisitionDecision::Approved) {
                $approved = $this->approveQuantities($requisition, $quantities);
                $this->move($requisition, S::Approved, RequisitionAction::Approved, $by, $comment,
                    ['approved_by' => $by->id, 'approved_at' => CarbonImmutable::now()], ['items' => $approved]);
                $this->notifier->send($requisition, 'approved', $this->notifier->requester($requisition), $by, $comment);
                $this->notifier->send($requisition, 'allocation', $this->notifier->holders('requisitions.fulfill', $requisition), $by);

                return;
            }
            if ($decision === RequisitionDecision::Rejected) {
                $this->move($requisition, S::Rejected, RequisitionAction::Rejected, $by, $comment,
                    ['rejection_reason' => $comment, 'rejected_at' => CarbonImmutable::now()]);
            } else {
                RequisitionItem::query()->where('requisition_id', $requisition->id)->update(['approved_quantity' => null]);
                $this->move($requisition, S::Draft, RequisitionAction::Returned, $by, $comment, ['reviewer_id' => null]);
            }
            $this->notifier->send($requisition, $decision->value, $this->notifier->requester($requisition), $by, $comment);
        });

        return $requisition;
    }

    public function markReady(Requisition $requisition, User $by): Requisition
    {
        DB::transaction(function () use ($requisition, $by): void {
            $this->lockFor('ready', $requisition, $by);
            $this->move($requisition, S::ReadyForAllocation, RequisitionAction::Ready, $by);
        });

        return $requisition;
    }

    /**
     * @param  list<array{id: int, approved_quantity: string|int|float}>  $quantities
     * @return array<int, string> approved quantity per item id
     */
    private function approveQuantities(Requisition $requisition, array $quantities): array
    {
        $items = RequisitionItem::query()->where('requisition_id', $requisition->id)->get()->keyBy('id');
        $given = [];
        foreach ($quantities as $i => $row) {
            $item = $items->get((int) $row['id']);
            $value = Decimal::of($row['approved_quantity']);
            if ($item === null || Decimal::cmp($value, '0') < 0 || Decimal::cmp($value, $item->quantity) > 0) {
                throw ValidationException::withMessages(["items.{$i}.approved_quantity" => __('messages.requisition_quantity_invalid')]);
            }
            $given[$item->id] = $value;
        }
        $approved = [];
        foreach ($items as $item) {
            $approved[$item->id] = $given[$item->id] ?? $item->quantity;
        }
        if (! array_filter($approved, fn (string $q) => Decimal::cmp($q, '0') > 0)) {
            throw ValidationException::withMessages(['items' => __('messages.requisition_nothing_approved')]);
        }
        foreach ($approved as $id => $quantity) {
            RequisitionItem::query()->whereKey($id)->update(['approved_quantity' => $quantity]);
        }

        return $approved;
    }

    /** Rejection and return need a reason; information requests and answers need text. */
    private function requireComment(?RequisitionDecision $decision, ?string $comment): void
    {
        if ($decision !== RequisitionDecision::Approved && trim((string) $comment) === '') {
            throw ValidationException::withMessages(['comment' => __('messages.requisition_comment_required')]);
        }
    }
}
