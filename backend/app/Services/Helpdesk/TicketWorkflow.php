<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketHistoryAction;
use App\Enums\TicketStatus;
use App\Events\Helpdesk\TicketAssigned;
use App\Events\Helpdesk\TicketCommented;
use App\Events\Helpdesk\TicketStatusChanged;
use App\Exceptions\InvalidTransitionException;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ticket status workflow (spec §4), assignment, conversation, rating and time
 * tracking. SLA effects: waiting_user pauses the resolution clock; the first
 * public handler action records the first response.
 */
class TicketWorkflow
{
    /** Status changes available to handlers (helpdesk.process with access). Assignment uses assign(). */
    public const HANDLER = [
        'new' => ['in_review', 'in_progress', 'rejected'],
        'in_review' => ['in_progress', 'rejected'],
        'assigned' => ['in_progress', 'waiting_user', 'resolved'],
        'in_progress' => ['waiting_user', 'resolved'],
        'waiting_user' => ['in_progress', 'resolved'],
        'resolved' => ['in_progress', 'closed'],
        'closed' => ['in_progress'],
        'rejected' => [],
    ];

    /** Status changes available to the requester: confirm (close) and reopen. */
    public const REQUESTER = [
        'resolved' => ['closed', 'in_progress'],
        'closed' => ['in_progress'],
    ];

    public function __construct(
        private readonly TicketService $tickets,
        private readonly BusinessCalendar $calendar,
        private readonly SettingsService $settings,
    ) {}

    /** @return list<string> target statuses $user may move $ticket to */
    public function allowedTargets(Ticket $ticket, User $user): array
    {
        $from = $ticket->status->value;
        $handler = $this->isHandler($ticket, $user);
        $requester = $ticket->isRequester($user);

        $targets = $handler ? self::HANDLER[$from] : [];
        if ($requester) {
            $targets = array_values(array_unique([...$targets, ...(self::REQUESTER[$from] ?? [])]));
        }

        $isReopen = in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true);
        $windowApplies = $ticket->status === TicketStatus::Closed || ! $handler;
        if ($isReopen && $windowApplies && ! $this->withinReopenWindow($ticket)) {
            $targets = array_values(array_diff($targets, [TicketStatus::InProgress->value]));
        }

        return $targets;
    }

    public function transition(Ticket $ticket, TicketStatus $to, User $by, ?string $note): Ticket
    {
        if (! $this->isHandler($ticket, $by) && ! $ticket->isRequester($by)) {
            throw new AuthorizationException;
        }
        if (! in_array($to->value, $this->allowedTargets($ticket, $by), true)) {
            $reopen = $to === TicketStatus::InProgress && in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true);
            throw new InvalidTransitionException($reopen ? __('messages.reopen_window_passed') : __('messages.invalid_transition'));
        }
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;
        if ($note === null && in_array($to, [TicketStatus::Resolved, TicketStatus::Rejected], true)) {
            throw ValidationException::withMessages(['note' => __('validation.required', ['attribute' => __('validation.attributes.note')])]);
        }

        return DB::transaction(function () use ($ticket, $to, $by, $note): Ticket {
            if ($to === TicketStatus::InProgress && $ticket->assignee_id === null && $this->isHandler($ticket, $by)) {
                $ticket->assignee_id = $by->id;
                $ticket->assigned_at = CarbonImmutable::now();
            }
            $this->applyStatus($ticket, $to, $by, $note);
            $this->recordFirstResponse($ticket, $by);

            return $ticket;
        });
    }

    public function assign(Ticket $ticket, User $assignee, User $by): Ticket
    {
        if (! $ticket->status->isOpen()) {
            throw new InvalidTransitionException(__('messages.ticket_closed'));
        }
        if (! $assignee->is_active || ! $assignee->can('helpdesk.process') || ! $assignee->canAccessOrganization($ticket->organization_id)) {
            throw ValidationException::withMessages(['assignee_id' => __('messages.assignee_invalid')]);
        }
        if ($ticket->assignee_id === $assignee->id) {
            return $ticket;
        }

        return DB::transaction(function () use ($ticket, $assignee, $by): Ticket {
            $old = ['assignee_id' => $ticket->assignee_id, 'status' => $ticket->status->value];
            $ticket->assignee_id = $assignee->id;
            $ticket->assigned_at = CarbonImmutable::now();
            if (in_array($ticket->status, [TicketStatus::New, TicketStatus::InReview], true)) {
                $ticket->status = TicketStatus::Assigned;
            }
            $ticket->save();

            $this->tickets->history($ticket, $by, TicketHistoryAction::Assigned, $old, [
                'assignee_id' => $assignee->id, 'assignee' => $assignee->name, 'status' => $ticket->status->value,
            ]);
            $this->recordFirstResponse($ticket, $by);
            TicketAssigned::dispatch($ticket, $assignee, $by);

            return $ticket;
        });
    }

    public function comment(Ticket $ticket, User $by, string $body, bool $internal): TicketComment
    {
        $handler = $this->isHandler($ticket, $by);
        $requester = $ticket->isRequester($by);
        if ((! $handler && ! $requester) || ($internal && ! $handler)) {
            throw new AuthorizationException;
        }
        if (! $internal && in_array($ticket->status, [TicketStatus::Closed, TicketStatus::Rejected], true)) {
            throw new InvalidTransitionException(__('messages.ticket_closed'));
        }

        return DB::transaction(function () use ($ticket, $by, $body, $internal, $requester): TicketComment {
            $comment = $ticket->comments()->create(['user_id' => $by->id, 'body' => $body, 'is_internal' => $internal]);
            $this->tickets->history($ticket, $by, TicketHistoryAction::Commented, new: ['comment_id' => $comment->id], internal: $internal);

            if (! $internal) {
                $this->recordFirstResponse($ticket, $by);
            }
            if ($requester && ! $internal && $ticket->status === TicketStatus::WaitingUser) {
                $this->applyStatus($ticket, TicketStatus::InProgress, $by, null);
            }
            TicketCommented::dispatch($ticket, $comment, $by);

            return $comment;
        });
    }

    public function rate(Ticket $ticket, int $rating, ?string $comment, User $by): Ticket
    {
        if (! $ticket->isRequester($by)) {
            throw new AuthorizationException;
        }
        if (! in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            throw new InvalidTransitionException(__('messages.rating_not_allowed'));
        }

        return DB::transaction(function () use ($ticket, $rating, $comment, $by): Ticket {
            $old = ['rating' => $ticket->rating];
            $ticket->forceFill(['rating' => $rating, 'rating_comment' => $comment, 'rated_at' => CarbonImmutable::now()])->save();
            $this->tickets->history($ticket, $by, TicketHistoryAction::Rated, $old, ['rating' => $rating], $comment);

            return $ticket;
        });
    }

    public function logTime(Ticket $ticket, int $minutes, ?string $note, User $by): Ticket
    {
        if (! $this->isHandler($ticket, $by)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($ticket, $minutes, $note, $by): Ticket {
            $ticket->time_spent_minutes += $minutes;
            $ticket->save();
            $this->tickets->history($ticket, $by, TicketHistoryAction::TimeLogged, new: ['minutes' => $minutes], note: $note, internal: true);

            return $ticket;
        });
    }

    /** Status change with its side effects, without permission checks (callers check). */
    public function applyStatus(Ticket $ticket, TicketStatus $to, ?User $by, ?string $note, TicketHistoryAction $action = TicketHistoryAction::StatusChanged): void
    {
        $from = $ticket->status;
        $now = CarbonImmutable::now();

        if ($from === TicketStatus::WaitingUser && $ticket->paused_at !== null) {
            $ticket->paused_minutes += $this->calendar->minutesBetween($ticket->paused_at, $now);
            $ticket->paused_at = null;
            $this->tickets->recomputeDueDates($ticket);
        }
        if ($to === TicketStatus::WaitingUser) {
            $ticket->paused_at = $now;
        }

        $reopened = $to === TicketStatus::InProgress && in_array($from, [TicketStatus::Resolved, TicketStatus::Closed], true);
        if ($reopened) {
            $ticket->reopened_count++;
            $ticket->resolved_at = null;
            $ticket->closed_at = null;
        }
        match ($to) {
            TicketStatus::Resolved => $ticket->forceFill(['resolved_at' => $now, 'resolution' => $note]),
            TicketStatus::Closed => $ticket->forceFill(['closed_at' => $now]),
            TicketStatus::Rejected => $ticket->forceFill(['rejected_at' => $now, 'reject_reason' => $note]),
            default => null,
        };
        $ticket->status = $to;
        $ticket->save();

        $this->tickets->history($ticket, $by, $action, ['status' => $from->value], [
            'status' => $to->value, ...($reopened ? ['reopened' => true] : []),
        ], $note);
        TicketStatusChanged::dispatch($ticket, $from, $to, $by);
    }

    public function isHandler(Ticket $ticket, User $user): bool
    {
        return $user->can('process', $ticket);
    }

    private function recordFirstResponse(Ticket $ticket, User $by): void
    {
        if ($ticket->first_response_at === null && $this->isHandler($ticket, $by) && ! $ticket->isRequester($by)) {
            $ticket->first_response_at = CarbonImmutable::now();
            $ticket->save();
        }
    }

    private function withinReopenWindow(Ticket $ticket): bool
    {
        $reference = $ticket->resolved_at ?? $ticket->closed_at;
        if ($reference === null) {
            return true;
        }

        return $reference->addDays((int) $this->settings->get('helpdesk.reopen_days'))->isFuture();
    }
}
