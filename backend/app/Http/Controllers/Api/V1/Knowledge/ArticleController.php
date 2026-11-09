<?php

namespace App\Http\Controllers\Api\V1\Knowledge;

use App\Enums\ArticleAudience;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Knowledge\ArticleResource;
use App\Models\KbArticle;
use App\Models\KbArticleAttachment;
use App\Models\KbArticleVote;
use App\Models\Ticket;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\Knowledge\Articles;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Knowledge base (design B1–B5). Route binding hides what the user may not read (404). */
class ArticleController extends Controller
{
    public function __construct(private readonly Articles $articles) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('kb.view');
        $user = $this->user($request);
        $query = $this->counted(KbArticle::query()->visibleTo($user)->with(['category', 'author']));
        foreach (['type' => 'type', 'category' => 'category_id', 'audience' => 'audience', 'status' => 'status'] as $filter => $column) {
            if ($request->filled("filter.{$filter}")) {
                $query->whereIn($column, explode(',', (string) $request->input("filter.{$filter}")));
            }
        }
        if ($request->boolean('filter.mine')) {
            $query->where('created_by', $user->id);
        }
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")
                ->orWhere('summary', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%"));
        }
        $page = $query->orderByRaw('published_at IS NULL')->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(Pagination::perPage($request))->withQueryString();

        return ApiResponse::page($page, fn (KbArticle $a) => ArticleResource::row($a));
    }

    /** Up to five published articles for the new-ticket form (design B4). */
    public function suggest(Request $request): JsonResponse
    {
        Gate::authorize('kb.view');
        $data = $request->validate(['title' => ['nullable', 'string', 'max:255'], 'category' => ['nullable', 'integer']]);
        $found = $this->articles->suggest($this->user($request), (string) ($data['title'] ?? ''), isset($data['category']) ? (int) $data['category'] : null);

        return ApiResponse::data($found->map(fn (KbArticle $a) => [
            'id' => $a->id, 'number' => $a->number, 'title' => $a->title, 'summary' => $a->summary, 'type' => $a->type->value,
        ])->values());
    }

    public function show(Request $request, KbArticle $kbArticle): JsonResponse
    {
        Gate::authorize('kb.view');
        $user = $this->user($request);
        $this->articles->viewed($kbArticle, $user);

        return $this->detail($kbArticle, $user);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('kb.write');
        $user = $this->user($request);
        $data = $request->validate([...$this->rules($user), 'source_ticket_id' => ['nullable', 'integer', $this->visibleTicket($user)], 'publish' => ['sometimes', 'boolean']]);

        return $this->detail($this->articles->create($data, $user), $user, 201);
    }

    public function update(Request $request, KbArticle $kbArticle): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->articles->canEdit($kbArticle, $user), 403);

        return $this->detail($this->articles->update($kbArticle, $request->validate($this->rules($user))), $user);
    }

    public function publish(Request $request, KbArticle $kbArticle): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->articles->canEdit($kbArticle, $user), 403);

        return $this->detail($this->articles->publish($kbArticle), $user);
    }

    public function archive(Request $request, KbArticle $kbArticle): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->articles->canEdit($kbArticle, $user), 403);

        return $this->detail($this->articles->archive($kbArticle), $user);
    }

    public function destroy(Request $request, KbArticle $kbArticle): JsonResponse
    {
        abort_unless($this->articles->canEdit($kbArticle, $this->user($request)), 403);
        $this->articles->delete($kbArticle);

        return ApiResponse::message(__('messages.deleted'));
    }

    public function vote(Request $request, KbArticle $kbArticle): JsonResponse
    {
        Gate::authorize('kb.view');
        abort_unless($kbArticle->status === ArticleStatus::Published, 404);
        $user = $this->user($request);
        $this->articles->vote($kbArticle, $user, $request->validate(['helpful' => ['required', 'boolean']])['helpful']);

        return $this->detail($kbArticle, $user);
    }

    public function attach(Request $request, KbArticle $kbArticle): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->articles->canEdit($kbArticle, $user), 403);
        $request->validate(['files' => ['required', 'array', 'max:5'], 'files.*' => ['required', new SafeUpload]]);
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter((array) $request->file('files'), fn ($f) => $f instanceof UploadedFile));
        $this->articles->attach($kbArticle, $files, $user);

        return $this->detail($kbArticle, $user, 201);
    }

    public function detach(Request $request, KbArticle $kbArticle, int $attachment): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->articles->canEdit($kbArticle, $user), 403);
        $this->articles->detach(KbArticleAttachment::query()->where('article_id', $kbArticle->id)->findOrFail($attachment));

        return $this->detail($kbArticle, $user);
    }

    private function detail(KbArticle $article, User $user, int $status = 200): JsonResponse
    {
        $fresh = $this->counted(KbArticle::query()->with(['category', 'author', 'attachments.file', 'sourceTicket']))->findOrFail($article->id);
        $vote = KbArticleVote::query()->where('article_id', $article->id)->where('user_id', $user->id)->value('helpful');
        $canEdit = $this->articles->canEdit($fresh, $user);

        return ApiResponse::data(ArticleResource::detail($fresh, $vote === null ? null : (bool) $vote, [
            'edit' => $canEdit,
            'delete' => $canEdit && $fresh->published_at === null,
        ]), $status);
    }

    /**
     * @param  Builder<KbArticle>  $query
     * @return Builder<KbArticle>
     */
    private function counted(Builder $query): Builder
    {
        return $query->withCount([
            'votes as helpful_count' => fn ($q) => $q->where('helpful', true),
            'votes as not_helpful_count' => fn ($q) => $q->where('helpful', false),
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(User $user): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ArticleType::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            'audience' => ['required', Rule::enum(ArticleAudience::class)],
        ];
    }

    private function visibleTicket(User $user): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($user): void {
            if (! Ticket::query()->visibleTo($user)->whereKey((int) $value)->exists()) {
                $fail(__('messages.ticket_not_accessible'));
            }
        };
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
