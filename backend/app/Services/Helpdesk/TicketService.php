<?php

namespace App\Services\Helpdesk;

use App\Enums\TicketHistoryAction;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\Helpdesk\TicketCreated;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Creating and editing tickets: numbering, requester defaults, SLA snapshot and
 * business-hours due dates, history.
 */
class TicketService
{
    /** Fields shown in the history when edited. */
    private const TRACKED = [
        'title', 'description', 'category_id', 'priority', 'department_id',
        'contact_name', 'contact_phone', 'contact_email', 'location',
    ];

    public function __construct(
        private readonly TicketNumberGenerator $numbers,
        private readonly SlaResolver $sla,
        private readonly BusinessCalendar $calendar,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated; organization_id resolved
     * @param  list<UploadedFile>  $files  already validated (SafeUpload)
     */
    public function create(array $data, User $by, array $files = []): Ticket
    {
        return DB::transaction(function () use ($data, $by, $files): Ticket {
            $now = CarbonImmutable::now();
            $organizationId = (int) ($data['organization_id'] ?? $by->organization_id);
            [$employee, $requesterUserId] = $this->resolveRequester($data, $by, $organizationId);

            $ticket = new Ticket([
                'number' => $this->numbers->next($now),
                'organization_id' => $organizationId,
                'department_id' => $data['department_id']
                    ?? ($employee?->organization_id === $organizationId ? $employee?->department_id : null),
                'requester_employee_id' => $employee?->id,
                'requester_user_id' => $requesterUserId,
                'contact_name' => $data['contact_name'] ?? $employee?->full_name ?? $by->name,
                'contact_phone' => $data['contact_phone'] ?? $employee?->phone,
                'contact_email' => $data['contact_email'] ?? $employee?->email ?? ($requesterUserId === $by->id ? $by->email : null),
                'location' => $data['location'] ?? null,
                'category_id' => (int) $data['category_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => TicketPriority::from((string) ($data['priority'] ?? TicketPriority::Normal->value)),
                'status' => TicketStatus::New,
                'created_by' => $by->id,
            ]);
            $ticket->created_at = $now;
            $this->applySla($ticket);
            $ticket->save();

            $this->history($ticket, $by, TicketHistoryAction::Created, new: [
                'status' => TicketStatus::New->value, 'priority' => $ticket->priority->value,
            ]);

            if ($files !== []) {
                app(TicketAttachments::class)->attach($ticket, $files, $by);
            }

            TicketCreated::dispatch($ticket, $by);

            return $ticket;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function update(Ticket $ticket, array $data, User $by): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $by): Ticket {
            $ticket->fill(array_intersect_key($data, array_flip(self::TRACKED)));
            if (! $ticket->isDirty()) {
                return $ticket;
            }

            $old = [];
            $new = [];
            foreach (array_keys($ticket->getDirty()) as $key) {
                $old[$key] = $this->plain($ticket->getOriginal($key));
                $new[$key] = $this->plain($ticket->getAttribute($key));
            }
            if ($ticket->isDirty(['priority', 'category_id'])) {
                $this->applySla($ticket);
            }
            $ticket->save();

            $this->history($ticket, $by, TicketHistoryAction::Updated, $old, $new);

            return $ticket;
        });
    }

    /**
     * Snapshot SLA targets and derive due dates from the creation time. Paused
     * (waiting for the user) business minutes push the resolution deadline.
     */
    public function applySla(Ticket $ticket): void
    {
        $targets = $this->sla->for($ticket->priority, $ticket->category_id);
        $ticket->sla_response_minutes = $targets['response'];
        $ticket->sla_resolution_minutes = $targets['resolution'];
        $this->recomputeDueDates($ticket);
    }

    public function recomputeDueDates(Ticket $ticket): void
    {
        $created = $ticket->created_at ?? CarbonImmutable::now();
        $ticket->response_due_at = $this->calendar->addMinutes($created, $ticket->sla_response_minutes);
        $ticket->resolution_due_at = $this->calendar->addMinutes(
            $created,
            $ticket->sla_resolution_minutes + (int) $ticket->paused_minutes,
        );

        $now = CarbonImmutable::now();
        if ($ticket->response_due_at->greaterThan($now)) {
            $ticket->response_breached_at = null;
        }
        if ($ticket->resolution_due_at->greaterThan($now)) {
            $ticket->resolution_breached_at = null;
            $ticket->due_soon_notified_at = null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function history(
        Ticket $ticket,
        ?User $by,
        TicketHistoryAction $action,
        ?array $old = null,
        ?array $new = null,
        ?string $note = null,
        bool $internal = false,
    ): TicketHistory {
        return TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $by?->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'note' => $note,
            'is_internal' => $internal,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{Employee|null, int|null}
     */
    private function resolveRequester(array $data, User $by, int $organizationId): array
    {
        if (! empty($data['requester_employee_id'])) {
            $employee = Employee::withoutGlobalScopes()->findOrFail((int) $data['requester_employee_id']);
            $userId = User::query()->where('employee_id', $employee->id)->value('id');

            return [$employee, $userId === null ? null : (int) $userId];
        }

        $own = $by->employee_id !== null ? Employee::withoutGlobalScopes()->find($by->employee_id) : null;

        return [$own?->organization_id === $organizationId ? $own : null, $by->id];
    }

    private function plain(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
