<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Enums\ColumnCategory;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tasks\TaskResource;
use App\Models\TaskBoard;
use App\Models\TaskColumn;
use App\Models\TaskRecurrence;
use App\Models\User;
use App\Services\Tasks\BoardService;
use App\Services\Tasks\RecurrenceSchedule;
use App\Services\Tasks\TaskService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Routine work of a board (tasks.manage). */
class RecurrenceController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly BoardService $boards,
        private readonly RecurrenceSchedule $schedule,
    ) {}

    public function index(TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.view');
        $rows = TaskRecurrence::query()->with('assignee')->where('board_id', $taskBoard->id)->orderBy('next_run_on')->get();

        return ApiResponse::data($rows->map(fn (TaskRecurrence $r) => $this->resource($r))->values());
    }

    public function store(Request $request, TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $this->boards->writable($taskBoard);
        $data = $this->validated($request, $taskBoard, null);
        $recurrence = TaskRecurrence::query()->create([...$data, 'board_id' => $taskBoard->id]);

        return ApiResponse::data($this->resource($recurrence->refresh()->load('assignee')), 201);
    }

    public function update(Request $request, TaskRecurrence $taskRecurrence): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $board = $this->visibleBoard($request, $taskRecurrence);
        $taskRecurrence->update($this->validated($request, $board, $taskRecurrence));

        return ApiResponse::data($this->resource($taskRecurrence->refresh()->load('assignee')));
    }

    public function destroy(Request $request, TaskRecurrence $taskRecurrence): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $this->visibleBoard($request, $taskRecurrence);
        $taskRecurrence->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, TaskBoard $board, ?TaskRecurrence $current): array
    {
        $creating = $current === null;
        $data = $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'column_id' => ['nullable', 'integer'],
            'organization_id' => ['nullable', 'integer'],
            'assignee_id' => ['nullable', 'integer'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer'],
            'frequency' => [$creating ? 'required' : 'sometimes', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['sometimes', 'integer', 'between:1,12'],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'month_day' => ['nullable', 'integer', 'between:1,28'],
            'due_in_days' => ['nullable', 'integer', 'between:0,60'],
            'start_on' => ['nullable', 'date', 'after_or_equal:today'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $rule = new TaskRecurrence([...($current?->only(['frequency', 'interval', 'weekday', 'month_day']) ?? []),
            ...array_intersect_key($data, array_flip(['frequency', 'interval', 'weekday', 'month_day']))]);
        $rule->interval ??= 1;
        if ($rule->frequency === RecurrenceFrequency::Weekly && $rule->weekday === null) {
            throw ValidationException::withMessages(['weekday' => __('validation.required', ['attribute' => 'weekday'])]);
        }
        if ($rule->frequency === RecurrenceFrequency::Monthly && $rule->month_day === null) {
            throw ValidationException::withMessages(['month_day' => __('validation.required', ['attribute' => 'month_day'])]);
        }
        $column = $this->tasks->columnOf($board, array_key_exists('column_id', $data) ? $data['column_id'] : $current?->column_id);
        if ($column->category !== ColumnCategory::Open) {
            throw ValidationException::withMessages(['column_id' => __('messages.recurrence_column_open')]);
        }
        $data['column_id'] = $column->id;
        $data['organization_id'] = $this->tasks->organizationFor($board, array_key_exists('organization_id', $data) ? $data['organization_id'] : $current?->organization_id);
        if (array_key_exists('assignee_id', $data)) {
            $data['assignee_id'] = $this->tasks->eligible($board, $data['assignee_id'], 'assignee_id');
        }
        if (array_key_exists('label_ids', $data)) {
            $data['label_ids'] = $this->tasks->labelIds($board, $data['label_ids']);
        }
        $ruleChanged = $creating || array_intersect_key($data, array_flip(['frequency', 'interval', 'weekday', 'month_day', 'start_on'])) !== [];
        if ($ruleChanged) {
            $start = isset($data['start_on']) ? CarbonImmutable::parse((string) $data['start_on']) : CarbonImmutable::today((string) config('app.display_timezone'));
            $data['next_run_on'] = $this->schedule->first($rule, $start);
        }
        unset($data['start_on']);

        return $data;
    }

    private function visibleBoard(Request $request, TaskRecurrence $recurrence): TaskBoard
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return TaskBoard::query()->visibleTo($user)->findOrFail($recurrence->board_id);
    }

    /** @return array<string, mixed> */
    private function resource(TaskRecurrence $r): array
    {
        $column = TaskColumn::query()->find($r->column_id);

        return [
            'id' => $r->id, 'board_id' => $r->board_id, 'title' => $r->title, 'description' => $r->description,
            'column' => $column ? TaskResource::column($column) : null, 'organization_id' => $r->organization_id,
            'assignee' => TaskResource::user($r->assignee), 'priority' => $r->priority->value, 'label_ids' => $r->label_ids ?? [],
            'frequency' => $r->frequency->value, 'interval' => $r->interval, 'weekday' => $r->weekday, 'month_day' => $r->month_day,
            'due_in_days' => $r->due_in_days, 'next_run_on' => $r->next_run_on->toDateString(), 'is_active' => $r->is_active,
        ];
    }
}
