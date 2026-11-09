<?php

namespace App\Http\Controllers\Api\V1\Tasks;

use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\TaskChecklistItem;
use App\Models\TaskColumn;
use App\Models\Ticket;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Tasks\TaskReports;
use App\Services\Tasks\TaskService;
use App\Support\ApiResponse;
use App\Support\OrganizationSubtree;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Tasks on boards the user can see (route binding applies the board scope). */
class TaskController extends Controller
{
    /** Done/cancelled columns show tasks finished within this many days (the rest stays searchable in lists). */
    private const DONE_DAYS = 30;

    public function __construct(private readonly TaskService $tasks) {}

    /** Kanban view: every column with its cards, filtered. */
    public function cards(Request $request, TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.view');
        $user = $this->user($request);
        $recent = CarbonImmutable::now()->subDays(self::DONE_DAYS);
        $columns = TaskColumn::query()->where('board_id', $taskBoard->id)->orderBy('position')->get();
        $cards = $this->cardQuery()->where('board_id', $taskBoard->id);
        $this->filter($cards, $request, $user);
        $cards = $cards->orderBy('position')->get()->groupBy('column_id');

        return ApiResponse::data(['columns' => $columns->map(function (TaskColumn $c) use ($cards, $recent) {
            $all = $cards->get($c->id, collect());
            $shown = $c->category->finishes() ? $all->filter(fn (Task $t) => $t->completed_at === null || $t->completed_at->gte($recent)) : $all;

            return [...TaskResource::column($c), 'cards' => $shown->map(fn (Task $t) => TaskResource::card($t))->values(), 'hidden_done' => $all->count() - $shown->count()];
        })->values()]);
    }

    /** Task list across boards (my tasks, reports drill-down). */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('tasks.view');
        $query = $this->cardQuery()->visibleTo($this->user($request));
        $this->filter($query, $request, $this->user($request));
        if ($request->filled('filter.board')) {
            $query->where('board_id', $request->integer('filter.board'));
        }
        if ($request->boolean('filter.open')) {
            $query->whereNull('completed_at');
        }
        $page = $query->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('id')->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (Task $t) => TaskResource::card($t));
    }

    /** Central IT head: load per person and organization. */
    public function workload(Request $request, TaskReports $reports): JsonResponse
    {
        Gate::authorize('tasks.workload');

        return ApiResponse::data($reports->workload($this->user($request), (array) $request->input('filter', [])));
    }

    public function store(Request $request, TaskBoard $taskBoard): JsonResponse
    {
        Gate::authorize('tasks.create');
        $task = $this->tasks->create($taskBoard, $request->validate($this->rules(true)), $this->user($request));

        return $this->detail($task, $request, 201);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('tasks.view');

        return $this->detail($task, $request);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorizeEdit($request, $task);

        return $this->detail($this->tasks->update($task, $request->validate($this->rules(false)), $this->user($request)), $request);
    }

    public function move(Request $request, Task $task): JsonResponse
    {
        $this->authorizeEdit($request, $task);
        $data = $request->validate(['column_id' => ['required', 'integer'], 'position' => ['required', 'integer', 'min:1']]);

        return $this->detail($this->tasks->move($task, (int) $data['column_id'], (int) $data['position'], $this->user($request)), $request);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        abort_unless($this->can($task, $this->user($request))['delete'], 403);
        $this->tasks->delete($task);

        return ApiResponse::message(__('messages.deleted'));
    }

    public function storeChecklist(Request $request, Task $task): JsonResponse
    {
        $this->authorizeEdit($request, $task);
        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        return $this->detail($this->tasks->addChecklistItem($task, $data['title'], $this->user($request)), $request, 201);
    }

    public function updateChecklist(Request $request, TaskChecklistItem $taskChecklistItem): JsonResponse
    {
        $task = $this->taskOf($request, $taskChecklistItem);
        $this->authorizeEdit($request, $task);
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:255'], 'is_done' => ['sometimes', 'boolean']]);

        return $this->detail($this->tasks->updateChecklistItem($taskChecklistItem, $data, $this->user($request)), $request);
    }

    public function destroyChecklist(Request $request, TaskChecklistItem $taskChecklistItem): JsonResponse
    {
        $task = $this->taskOf($request, $taskChecklistItem);
        $this->authorizeEdit($request, $task);

        return $this->detail($this->tasks->deleteChecklistItem($taskChecklistItem, $this->user($request)), $request);
    }

    public function comment(Request $request, Task $task): JsonResponse
    {
        abort_unless($this->can($task, $this->user($request))['comment'], 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $this->tasks->comment($task, $data['body'], $this->user($request));

        return $this->detail($task, $request, 201);
    }

    public function attach(Request $request, Task $task): JsonResponse
    {
        abort_unless($this->can($task, $this->user($request))['comment'], 403);
        $request->validate(['files' => ['required', 'array', 'max:5'], 'files.*' => ['required', new SafeUpload]]);
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('files'), fn ($f) => $f instanceof UploadedFile));

        return $this->detail($this->tasks->attach($task, $files, $this->user($request)), $request, 201);
    }

    /** A Helpdesk ticket becomes a linked task (title, description and organization are copied). */
    public function fromTicket(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('tasks.create');
        $user = $this->user($request);
        abort_unless(Ticket::query()->visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        $data = $request->validate([
            'board_id' => ['required', 'integer'],
            'column_id' => ['nullable', 'integer'],
            'assignee_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);
        $board = TaskBoard::query()->visibleTo($user)->findOrFail((int) $data['board_id']);
        $organizationId = in_array($ticket->organization_id, OrganizationSubtree::ids($board->organization_id), true) ? $ticket->organization_id : null;
        $task = $this->tasks->create($board, [
            'title' => $ticket->title, 'description' => $ticket->description, 'organization_id' => $organizationId,
            'column_id' => $data['column_id'] ?? null, 'assignee_id' => $data['assignee_id'] ?? null, 'due_date' => $data['due_date'] ?? null,
            'ticket_id' => $ticket->id,
        ], $user);

        return $this->detail($task, $request, 201);
    }

    public function ticketTasks(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('tasks.view');
        $user = $this->user($request);
        abort_unless(Ticket::query()->visibleTo($user)->whereKey($ticket->id)->exists(), 404);
        $rows = $this->cardQuery()->visibleTo($user)->where('ticket_id', $ticket->id)->orderBy('id')->get();

        return ApiResponse::data($rows->map(fn (Task $t) => TaskResource::card($t))->values());
    }

    /** @return Builder<Task> */
    private function cardQuery(): Builder
    {
        return Task::query()->with(TaskResource::CARD_RELATIONS)->withCount([
            'checklist as checklist_total',
            'checklist as checklist_done' => fn ($q) => $q->where('is_done', true),
            'comments as comments_count',
        ]);
    }

    /** @param  Builder<Task>  $query */
    private function filter(Builder $query, Request $request, User $user): void
    {
        $today = CarbonImmutable::today((string) config('app.display_timezone'))->toDateString();
        $query
            ->when($request->boolean('filter.mine'), fn ($q) => $q->where('assignee_id', $user->id))
            ->when($request->filled('filter.assignee'), fn ($q) => $q->where('assignee_id', $request->integer('filter.assignee')))
            ->when($request->filled('filter.label'), fn ($q) => $q->whereHas('labels', fn ($l) => $l->whereKey($request->integer('filter.label'))))
            ->when($request->filled('filter.organization'), fn ($q) => $q->whereIn('organization_id', OrganizationSubtree::ids($request->integer('filter.organization')) ?: [0]))
            ->when($request->boolean('filter.overdue'), fn ($q) => $q->whereNull('completed_at')->where('due_date', '<', $today));
        $search = trim((string) $request->input('search', $request->input('filter.search')));
        if ($search !== '') {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%")->orWhere('project', 'like', "%{$search}%"));
        }
    }

    /** @return array<string, mixed> */
    private function rules(bool $creating): array
    {
        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'column_id' => [$creating ? 'nullable' : 'prohibited', 'integer'],
            'organization_id' => ['nullable', 'integer'],
            'project' => ['nullable', 'string', 'max:150'],
            'assignee_id' => ['nullable', 'integer'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer'],
            'watcher_ids' => ['sometimes', 'array', 'max:20'],
            'watcher_ids.*' => ['integer'],
        ];
    }

    /** @return array<string, bool> */
    private function can(Task $task, User $user): array
    {
        $archived = $task->board->is_archived;
        $edit = ! $archived && $this->tasks->canEdit($task, $user);

        return [
            'edit' => $edit,
            'comment' => ! $archived && ($edit || $user->can('tasks.create')),
            'delete' => ! $archived && ($user->can('tasks.manage')
                || ($task->created_by === $user->id && ! $task->comments()->exists() && ! $task->attachments()->exists())),
        ];
    }

    private function authorizeEdit(Request $request, Task $task): void
    {
        abort_unless($this->tasks->canEdit($task, $this->user($request)), 403);
    }

    private function taskOf(Request $request, TaskChecklistItem $item): Task
    {
        return Task::query()->visibleTo($this->user($request))->findOrFail($item->task_id);
    }

    private function detail(Task $task, Request $request, int $status = 200): JsonResponse
    {
        $task = Task::query()->withCount([
            'checklist as checklist_total',
            'checklist as checklist_done' => fn ($q) => $q->where('is_done', true),
            'comments as comments_count',
        ])->findOrFail($task->id);
        $task->load([...TaskResource::CARD_RELATIONS, 'board', 'creator', 'ticket', 'watchers', 'checklist', 'comments.user',
            'attachments.file', 'attachments.uploader', 'histories' => fn ($q) => $q->with('user')->latest('id')->limit(100)]);

        return ApiResponse::data(TaskResource::detail($task, $this->can($task, $this->user($request))), $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
