<?php

namespace App\Services\Problems;

use App\Enums\ProblemEvent;
use App\Enums\ProblemStatus;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\KbArticle;
use App\Models\Problem;
use App\Models\ProblemHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Itsm\ProblemNotification;
use App\Services\Settings\SettingsService;
use App\Services\Support\NumberSequence;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/** Problem management (design R1–R6). Every change re-reads the problem under a row lock. */
class ProblemService
{
    private const RECURRENCE_DAYS = 30;

    public function __construct(private readonly NumberSequence $numbers, private readonly SettingsService $settings) {}

    /** @param  array<string, mixed>  $data  validated: organization_id, title, description, category_id, priority, assignee_id?, ticket_ids? */
    public function create(array $data, User $by): Problem
    {
        $problem = DB::transaction(function () use ($data, $by): Problem {
            $problem = new Problem(array_intersect_key($data, array_flip(['organization_id', 'title', 'description', 'category_id', 'priority'])));
            $year = CarbonImmutable::now((string) config('app.display_timezone'))->year;
            $problem->forceFill([
                'number' => sprintf('PRB-%d-%05d', $year, $this->numbers->next("problem:{$year}")),
                'status' => ProblemStatus::New,
                'assignee_id' => $this->eligible($problem->organization_id, $data['assignee_id'] ?? null),
                'created_by' => $by->id,
            ])->save();
            $this->record($problem, ProblemEvent::Created, $by);
            if (! empty($data['ticket_ids'])) {
                $this->attachTickets($problem, (array) $data['ticket_ids'], $by);
            }

            return $problem;
        });
        $this->notifyAssignee($problem, null, $by);

        return $problem;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Problem $problem, array $data, User $by): Problem
    {
        $before = $problem->assignee_id;
        $problem = $this->locked($problem, function (Problem $p) use ($data, $by): void {
            if ($p->status === ProblemStatus::Closed) {
                throw new InvalidTransitionException(__('messages.problem_closed'));
            }
            $fields = array_intersect_key($data, array_flip(['title', 'description', 'category_id', 'priority', 'root_cause', 'workaround', 'resolution', 'kb_article_id']));
            $p->fill($fields);
            $requested = $data['assignee_id'] ?? null;
            if (array_key_exists('assignee_id', $data) && ($requested === null || $requested === '' ? null : (int) $requested) !== $p->assignee_id) {
                $p->assignee_id = $this->eligible($p->organization_id, $requested);
            }
            $changed = array_keys($p->getDirty());
            $p->save();
            if (in_array('assignee_id', $changed, true)) {
                $this->record($p, ProblemEvent::Assigned, $by, ['assignee' => $p->assignee?->name]);
            }
            $rest = array_values(array_diff($changed, ['assignee_id', 'updated_by']));
            if ($rest !== []) {
                $this->record($p, ProblemEvent::Updated, $by, ['fields' => $rest]);
            }
        });
        $this->notifyAssignee($problem, $before, $by);

        return $problem;
    }

    /** @param  array<string, mixed>  $data  status + root_cause/workaround/resolution/reason */
    public function transition(Problem $problem, ProblemStatus $target, array $data, User $by): Problem
    {
        $problem = $this->locked($problem, function (Problem $p) use ($target, $data, $by): void {
            $from = $p->status;
            if (! in_array($target, $from->next(), true)) {
                throw new InvalidTransitionException(__('messages.invalid_transition'));
            }
            foreach (['root_cause', 'workaround', 'resolution'] as $field) {
                if (filled($data[$field] ?? null)) {
                    $p->{$field} = (string) $data[$field];
                }
            }
            $missing = match ($target) {
                ProblemStatus::KnownError => array_filter(['workaround' => $p->workaround], fn ($v) => blank($v)),
                ProblemStatus::Resolved => array_filter(['root_cause' => $p->root_cause, 'resolution' => $p->resolution], fn ($v) => blank($v)),
                ProblemStatus::Closed => $from !== ProblemStatus::Resolved && blank($data['reason'] ?? null) ? ['reason' => null] : [],
                default => [],
            };
            if ($missing !== []) {
                throw ValidationException::withMessages(array_map(fn () => __('messages.problem_field_required'), $missing));
            }
            $now = CarbonImmutable::now();
            $p->status = $target;
            $p->resolved_at = match ($target) {
                ProblemStatus::Resolved => $now,
                ProblemStatus::Closed => $p->resolved_at,
                default => null,
            };
            if ($target === ProblemStatus::Closed) {
                $p->closed_at = $now;
                $p->close_reason = filled($data['reason'] ?? null) ? (string) $data['reason'] : null;
            }
            $p->save();
            $this->record($p, ProblemEvent::StatusChanged, $by, ['from' => $from->value, 'to' => $target->value], $data['reason'] ?? null);
        });

        if ($target === ProblemStatus::Resolved) {
            $assignees = User::query()->withoutGlobalScopes()->where('is_active', true)->where('id', '!=', $by->id)
                ->whereIn('id', $problem->tickets()->whereIn('status', TicketStatus::openValues())->whereNotNull('assignee_id')->select('assignee_id'))->get();
            Notification::send($assignees, new ProblemNotification('resolved', $problem->id, $problem->number, $problem->title, $by->name));
        }

        return $problem;
    }

    /** @param  array<int, mixed>  $ticketIds */
    public function link(Problem $problem, array $ticketIds, User $by): Problem
    {
        return $this->locked($problem, function (Problem $p) use ($ticketIds, $by): void {
            if ($p->status === ProblemStatus::Closed) {
                throw new InvalidTransitionException(__('messages.problem_closed'));
            }
            $this->attachTickets($p, $ticketIds, $by);
        });
    }

    public function unlink(Problem $problem, Ticket $ticket, User $by): Problem
    {
        return $this->locked($problem, function (Problem $p) use ($ticket, $by): void {
            if ($p->tickets()->detach($ticket->id) > 0) {
                $this->record($p, ProblemEvent::TicketUnlinked, $by, ['number' => $ticket->number]);
            }
        });
    }

    public function comment(Problem $problem, string $comment, User $by): void
    {
        $this->record($problem, ProblemEvent::Commented, $by, null, $comment);
    }

    /**
     * Category × organization pairs with at least the threshold of tickets in the last 30 days that no open problem covers
     * yet (design R4), within what the user can see.
     *
     * @return list<array{category_id: int, organization_id: int, count: int, ticket_ids: list<int>, last_at: string}>
     */
    public function candidates(User $user, ?int $organizationId): array
    {
        $since = CarbonImmutable::now()->subDays(self::RECURRENCE_DAYS);
        $threshold = (int) $this->settings->get('problems.recurrence_threshold');
        $covered = DB::table('problem_tickets')->join('problems', 'problems.id', '=', 'problem_tickets.problem_id')
            ->whereIn('problems.status', ProblemStatus::openValues())->select('problem_tickets.ticket_id');

        $tickets = Ticket::query()->visibleTo($user)->where('helpdesk_tickets.created_at', '>=', $since)
            ->whereNotIn('helpdesk_tickets.id', $covered)
            ->when($organizationId !== null, fn ($q) => $q->whereIn('helpdesk_tickets.organization_id', OrganizationSubtree::ids($organizationId) ?: [0]))
            ->orderBy('helpdesk_tickets.id')
            ->get(['helpdesk_tickets.id', 'helpdesk_tickets.category_id', 'helpdesk_tickets.organization_id', 'helpdesk_tickets.created_at']);

        $groups = [];
        foreach ($tickets as $ticket) {
            $key = $ticket->category_id.':'.$ticket->organization_id;
            $groups[$key] ??= ['category_id' => $ticket->category_id, 'organization_id' => $ticket->organization_id, 'count' => 0, 'ticket_ids' => [], 'last_at' => ''];
            $groups[$key]['count']++;
            $groups[$key]['ticket_ids'][] = $ticket->id;
            $groups[$key]['last_at'] = max($groups[$key]['last_at'], $ticket->created_at->toIso8601String());
        }
        $rows = array_values(array_filter($groups, fn (array $g) => $g['count'] >= $threshold));
        usort($rows, fn (array $a, array $b) => $b['count'] <=> $a['count'] ?: strcmp($b['last_at'], $a['last_at']));

        return $rows;
    }

    /** A known-error article must be one the user can read. */
    public function articleVisible(User $user, int $articleId): bool
    {
        return KbArticle::query()->visibleTo($user)->whereKey($articleId)->exists();
    }

    /** Responsible person: an active user who works on problems and can open the organization (design R1). */
    public function eligible(int $organizationId, mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        $user = User::query()->withoutGlobalScopes()->where('is_active', true)->find((int) $userId);
        if ($user === null || ! $user->can('problems.manage') || ! $user->canAccessOrganization($organizationId)) {
            throw ValidationException::withMessages(['assignee_id' => __('messages.problem_assignee_invalid')]);
        }

        return $user->id;
    }

    /** @param  array<int, mixed>  $ticketIds */
    private function attachTickets(Problem $problem, array $ticketIds, User $by): void
    {
        $ids = array_values(array_unique(array_map('intval', $ticketIds)));
        $allowed = Ticket::query()->visibleTo($by)->whereIn('helpdesk_tickets.id', $ids)
            ->whereIn('helpdesk_tickets.organization_id', OrganizationSubtree::ids($problem->organization_id) ?: [0])
            ->pluck('helpdesk_tickets.number', 'helpdesk_tickets.id');
        if ($allowed->count() !== count($ids)) {
            throw ValidationException::withMessages(['ticket_ids' => __('messages.problem_ticket_invalid')]);
        }
        $new = array_values(array_diff($ids, $problem->tickets()->pluck('helpdesk_tickets.id')->map(fn ($id) => (int) $id)->all()));
        if ($new === []) {
            return;
        }
        $problem->tickets()->attach(array_fill_keys($new, ['linked_by' => $by->id, 'created_at' => CarbonImmutable::now()]));
        $this->record($problem, ProblemEvent::TicketsLinked, $by, ['numbers' => array_map(fn ($id) => $allowed[$id], $new)]);
    }

    /** @param  Closure(Problem): void  $work */
    private function locked(Problem $problem, Closure $work): Problem
    {
        return DB::transaction(function () use ($problem, $work): Problem {
            $locked = Problem::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($problem->id);
            $work($locked);

            return $locked;
        });
    }

    /** @param  array<string, mixed>|null  $payload */
    private function record(Problem $problem, ProblemEvent $event, User $by, ?array $payload = null, ?string $comment = null): void
    {
        ProblemHistory::query()->create(['problem_id' => $problem->id, 'event' => $event, 'payload' => $payload, 'comment' => $comment, 'user_id' => $by->id]);
    }

    private function notifyAssignee(Problem $problem, ?int $before, User $by): void
    {
        if ($problem->assignee_id !== null && $problem->assignee_id !== $before && $problem->assignee_id !== $by->id) {
            $assignee = User::query()->withoutGlobalScopes()->find($problem->assignee_id);
            $assignee?->notify(new ProblemNotification('assigned', $problem->id, $problem->number, $problem->title, $by->name));
        }
    }
}
