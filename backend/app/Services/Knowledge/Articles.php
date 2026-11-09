<?php

namespace App\Services\Knowledge;

use App\Enums\ArticleStatus;
use App\Exceptions\DomainConflictException;
use App\Models\KbArticle;
use App\Models\KbArticleAttachment;
use App\Models\KbArticleVote;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\FileStore;
use App\Services\Support\NumberSequence;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Knowledge base articles (design B1–B5). */
class Articles
{
    private const SUGGESTIONS = 5;

    public function __construct(private readonly NumberSequence $numbers, private readonly FileStore $files) {}

    /** @param  array<string, mixed>  $data  validated fields + publish? */
    public function create(array $data, User $by): KbArticle
    {
        return DB::transaction(function () use ($data, $by): KbArticle {
            $article = new KbArticle(array_intersect_key($data, array_flip((new KbArticle)->getFillable())));
            $article->forceFill([
                'number' => sprintf('KB-%05d', $this->numbers->next('kb')),
                'status' => ArticleStatus::Draft,
                'created_by' => $by->id,
            ])->save();

            return ! empty($data['publish']) ? $this->publish($article) : $article;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(KbArticle $article, array $data): KbArticle
    {
        $article->fill(array_intersect_key($data, array_flip(['title', 'type', 'category_id', 'summary', 'body', 'audience'])))->save();

        return $article;
    }

    public function publish(KbArticle $article): KbArticle
    {
        $article->forceFill(['status' => ArticleStatus::Published, 'published_at' => $article->published_at ?? CarbonImmutable::now()])->save();

        return $article;
    }

    public function archive(KbArticle $article): KbArticle
    {
        $article->forceFill(['status' => ArticleStatus::Archived])->save();

        return $article;
    }

    /** Only articles that were never published disappear; the rest are archived (design B3). */
    public function delete(KbArticle $article): void
    {
        DB::transaction(function () use ($article): void {
            $locked = KbArticle::query()->lockForUpdate()->findOrFail($article->id);
            if ($locked->published_at !== null) {
                throw new DomainConflictException(__('messages.kb_article_was_published'));
            }
            $files = StoredFile::query()->whereIn('id', KbArticleAttachment::query()->where('article_id', $locked->id)->select('file_id'))->get();
            $locked->delete();
            DB::afterCommit(fn () => $files->each(fn (StoredFile $f) => $this->files->delete($f)));
        });
    }

    /** One vote per user; voting again changes it. */
    public function vote(KbArticle $article, User $user, bool $helpful): void
    {
        KbArticleVote::query()->updateOrCreate(['article_id' => $article->id, 'user_id' => $user->id], ['helpful' => $helpful]);
    }

    /** Readers other than the author count as views. */
    public function viewed(KbArticle $article, User $user): void
    {
        if ($article->created_by !== $user->id && $article->status === ArticleStatus::Published) {
            KbArticle::query()->whereKey($article->id)->increment('views');
            $article->views++;
        }
    }

    /** @param  list<UploadedFile>  $uploads */
    public function attach(KbArticle $article, array $uploads, User $by): void
    {
        $stored = array_map(fn (UploadedFile $u) => $this->files->store($u, null, $by), $uploads);
        DB::transaction(function () use ($article, $stored, $by): void {
            foreach ($stored as $file) {
                KbArticleAttachment::query()->create(['article_id' => $article->id, 'file_id' => $file->id, 'uploaded_by' => $by->id]);
            }
        });
    }

    public function detach(KbArticleAttachment $attachment): void
    {
        $file = $attachment->file;
        $attachment->delete();
        $this->files->delete($file);
    }

    /** kb.manage, or kb.write on one's own article. */
    public function canEdit(KbArticle $article, User $user): bool
    {
        return $user->can('kb.manage') || ($user->can('kb.write') && $article->created_by === $user->id);
    }

    /** FileAccess resolver: files follow their article's visibility. */
    public function canDownload(User $user, StoredFile $file): ?bool
    {
        $articleId = KbArticleAttachment::query()->where('file_id', $file->id)->value('article_id');
        if ($articleId === null) {
            return null;
        }

        return $user->can('kb.view') && KbArticle::query()->visibleTo($user)->whereKey($articleId)->exists();
    }

    /**
     * Published articles the user may read whose title or summary shares a word stem with $title; the ticket's category
     * first (design B4).
     *
     * @return Collection<int, KbArticle>
     */
    public function suggest(User $user, string $title, ?int $categoryId): Collection
    {
        $stems = collect(preg_split('/[^\p{L}\p{N}\'ʻʼ‘’`]+/u', mb_strtolower($title)) ?: [])
            ->filter(fn (string $w) => mb_strlen($w) >= 3)->map(fn (string $w) => mb_substr($w, 0, 5))->unique()->take(8)->values();
        if ($stems->isEmpty()) {
            return new Collection;
        }

        return KbArticle::query()->visibleTo($user)->where('status', ArticleStatus::Published->value)->with('category')
            ->where(function ($q) use ($stems): void {
                foreach ($stems as $stem) {
                    $q->orWhere('title', 'like', "%{$stem}%")->orWhere('summary', 'like', "%{$stem}%");
                }
            })
            ->when($categoryId !== null, fn ($q) => $q->orderByRaw('category_id = ? DESC', [$categoryId]))
            ->orderByDesc('views')->orderByDesc('id')->limit(self::SUGGESTIONS)->get()->toBase();
    }
}
