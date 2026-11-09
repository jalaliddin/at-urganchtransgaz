<?php

namespace App\Console\Commands\Tasks;

use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskLabel;
use App\Models\TaskRecurrence;
use App\Models\User;
use App\Notifications\Tasks\TaskNotification;
use App\Services\Tasks\RecurrenceSchedule;
use App\Services\Tasks\TaskService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Daily: recurring tasks for today (missed days catch up with one task only) and reminders once per task and
 * due date (due tomorrow, overdue) to the responsible person and watchers. No authenticated user: explicit scope.
 */
class TaskDailyCommand extends Command
{
    protected $signature = 'tasks:daily';

    protected $description = 'Create recurring tasks and send task due/overdue reminders';

    public function handle(TaskService $tasks, RecurrenceSchedule $schedule): int
    {
        // The local calendar date, as a date in the app timezone (stored dates are compared the same way).
        $today = CarbonImmutable::parse(CarbonImmutable::today((string) config('app.display_timezone'))->toDateString());
        $created = 0;
        $due = TaskRecurrence::query()->where('is_active', true)->where('next_run_on', '<=', $today->toDateString())->orderBy('id')->get();
        foreach ($due as $recurrence) {
            $occurrence = $recurrence->next_run_on;
            while ($schedule->advance($recurrence, $occurrence)->lte($today)) {
                $occurrence = $schedule->advance($recurrence, $occurrence);
            }
            $created += $this->occur($tasks, $recurrence, $occurrence) ? 1 : 0;
            $recurrence->forceFill(['next_run_on' => $schedule->advance($recurrence, $occurrence)])->save();
        }

        $sent = $this->remind('due_soon', Task::query()->whereNull('completed_at')->whereDate('due_date', $today->addDay()))
            + $this->remind('overdue', Task::query()->whereNull('completed_at')->whereDate('due_date', '<', $today));
        $this->info("tasks created: {$created}, reminders: {$sent}");

        return self::SUCCESS;
    }

    /** One task per recurrence and occurrence date (UNIQUE); archived boards are skipped. */
    private function occur(TaskService $tasks, TaskRecurrence $recurrence, CarbonImmutable $occurrence): bool
    {
        $board = TaskBoard::query()->withoutGlobalScopes()->find($recurrence->board_id);
        if ($board === null || $board->is_archived) {
            return false;
        }
        // Labels deleted since, or a responsible person who can no longer work on the board, never drop the occurrence.
        $labels = TaskLabel::query()->where('board_id', $board->id)->whereIn('id', $recurrence->label_ids ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        try {
            $assignee = $tasks->eligible($board, $recurrence->assignee_id, 'assignee_id');
        } catch (ValidationException) {
            $assignee = null;
        }
        $by = $recurrence->created_by === null ? null : User::query()->withoutGlobalScopes()->find($recurrence->created_by);
        try {
            $tasks->create($board, [
                'title' => $recurrence->title, 'description' => $recurrence->description, 'column_id' => $recurrence->column_id,
                'organization_id' => $recurrence->organization_id, 'priority' => $recurrence->priority, 'label_ids' => $labels,
                'assignee_id' => $assignee, 'due_date' => $recurrence->due_in_days === null ? null : $occurrence->addDays($recurrence->due_in_days),
                'recurrence_id' => $recurrence->id, 'occurrence_date' => $occurrence,
            ], $by);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** @param  Builder<Task>  $query */
    private function remind(string $event, $query): int
    {
        $sent = 0;
        foreach ($query->with(['watchers', 'assignee'])->get() as $task) {
            $fresh = DB::table('task_alerts')->insertOrIgnore([
                'task_id' => $task->id, 'type' => $event, 'due_date' => $task->due_date?->toDateString(), 'created_at' => CarbonImmutable::now(),
            ]);
            $recipients = $task->watchers->push($task->assignee)->filter()->unique('id');
            if ($fresh === 0 || $recipients->isEmpty()) {
                continue;
            }
            Notification::send($recipients, new TaskNotification($event, $task->id, $task->number, $task->title, '', (string) $task->due_date?->format('d.m.Y')));
            $sent++;
        }

        return $sent;
    }
}
