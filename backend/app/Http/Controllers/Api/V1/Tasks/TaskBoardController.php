<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Enums\ColumnCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tasks\TaskResource;
use App\Models\TaskBoard;
use App\Models\TaskColumn;
use App\Models\TaskLabel;
use App\Models\User;
use App\Rules\AccessibleOrganization;
use App\Services\Tasks\BoardService;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Boards of the user's organizations, their columns and labels. */
class TaskBoardController extends Controller
{
    public function __construct(private readonly BoardService $boards) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('tasks.view');
        $today = CarbonImmutable::today((string) config('app.display_timezone'))->toDateString();
        $rows = TaskBoard::query()->visibleTo($this->user($request))->with('organization')
            ->where('is_archived', $request->boolean('filter.archived'))
            ->when($request->filled('filter.organization'), fn ($q) => $q->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]))
            ->addSelect(['open_tasks' => DB::table('tasks')->selectRaw('COUNT(*)')->whereColumn('tasks.board_id', 'task_boards.id')->whereNull('completed_at')])
            ->addSelect(['overdue_tasks' => DB::table('tasks')->selectRaw('COUNT(*)')->whereColumn('tasks.board_id', 'task_boards.id')
                ->whereNull('completed_at')->where('due_date', '<', $today)])
            ->orderBy('name')->get();

        return ApiResponse::data($rows->map(fn (TaskBoard $b) => TaskResource::board($b))->values());
    }

    public function show(TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.view');

        return $this->card($taskBoard);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $data = $request->validate([
            'organization_id' => ['required', 'integer', new AccessibleOrganization],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->card($this->boards->create($data, $this->user($request)), 201);
    }

    public function update(Request $request, TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $taskBoard->update($request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_archived' => ['sometimes', 'boolean'],
        ]));

        return $this->card($taskBoard);
    }

    public function destroy(TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $this->boards->delete($taskBoard);

        return ApiResponse::message(__('messages.deleted'));
    }

    public function storeColumn(Request $request, TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::enum(ColumnCategory::class)],
            'wip_limit' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        return ApiResponse::data(TaskResource::column($this->boards->addColumn($taskBoard, $data)), 201);
    }

    public function updateColumn(Request $request, TaskColumn $taskColumn): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', Rule::enum(ColumnCategory::class)],
            'wip_limit' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        return ApiResponse::data(TaskResource::column($this->boards->updateColumn($taskColumn, $data)));
    }

    public function destroyColumn(TaskColumn $taskColumn): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $this->boards->deleteColumn($taskColumn);

        return ApiResponse::message(__('messages.deleted'));
    }

    public function orderColumns(Request $request, TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $this->boards->reorder($taskBoard, $data['ids']);

        return $this->card($taskBoard);
    }

    public function storeLabel(Request $request, TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $this->boards->writable($taskBoard);
        $label = TaskLabel::query()->create([...$request->validate($this->labelRules($taskBoard->id, null)), 'board_id' => $taskBoard->id]);

        return ApiResponse::data(TaskResource::label($label), 201);
    }

    public function updateLabel(Request $request, TaskLabel $taskLabel): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $this->boards->writable(TaskBoard::query()->withoutGlobalScopes()->findOrFail($taskLabel->board_id));
        $taskLabel->update($request->validate($this->labelRules($taskLabel->board_id, $taskLabel->id)));

        return ApiResponse::data(TaskResource::label($taskLabel));
    }

    public function destroyLabel(TaskLabel $taskLabel): JsonResponse
    {
        Gate::authorize('tasks.manage');
        $this->boards->writable(TaskBoard::query()->withoutGlobalScopes()->findOrFail($taskLabel->board_id));
        $taskLabel->delete();

        return ApiResponse::message(__('messages.deleted'));
    }

    /** @return array<string, mixed> */
    private function labelRules(int $boardId, ?int $ignore): array
    {
        return [
            'name' => [$ignore ? 'sometimes' : 'required', 'string', 'max:50', Rule::unique('task_labels', 'name')->where('board_id', $boardId)->ignore($ignore)],
            'color' => ['sometimes', 'string', Rule::in(['gray', 'red', 'orange', 'yellow', 'green', 'teal', 'blue', 'indigo', 'violet', 'pink'])],
        ];
    }

    private function card(TaskBoard $board, int $status = 200): JsonResponse
    {
        $board->refresh()->load(['organization', 'columns', 'labels']);

        return ApiResponse::data([
            ...TaskResource::board($board),
            'columns' => $board->columns->map(fn (TaskColumn $c) => TaskResource::column($c))->values(),
            'labels' => $board->labels->map(fn (TaskLabel $l) => TaskResource::label($l))->values(),
        ], $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
