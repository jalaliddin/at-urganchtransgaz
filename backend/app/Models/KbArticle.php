<?php

namespace App\Models;

use App\Enums\ArticleAudience;
use App\Enums\ArticleStatus;
use App\Enums\ArticleType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Knowledge base article (design B1). Global; who may read it depends on its
 * audience and status (design B3).
 *
 * @property int $id
 * @property string $number
 * @property string $title
 * @property ArticleType $type
 * @property int|null $category_id
 * @property string|null $summary
 * @property string $body
 * @property ArticleAudience $audience
 * @property ArticleStatus $status
 * @property CarbonImmutable|null $published_at
 * @property int $views
 * @property int|null $source_ticket_id
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read TicketCategory|null $category
 * @property-read User|null $author
 * @property-read Ticket|null $sourceTicket
 */
class KbArticle extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['title', 'type', 'category_id', 'summary', 'body', 'audience', 'source_ticket_id'];

    protected function casts(): array
    {
        return [
            'type' => ArticleType::class,
            'audience' => ArticleAudience::class,
            'status' => ArticleStatus::class,
            'published_at' => 'immutable_datetime',
            'views' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Articles $user may read: published ones of an allowed audience, their own
     * drafts/archive, everything for kb.manage.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('kb.manage')) {
            return;
        }
        $audiences = $user->can('kb.internal') ? [ArticleAudience::All->value, ArticleAudience::It->value] : [ArticleAudience::All->value];
        $query->where(function (Builder $q) use ($user, $audiences): void {
            $q->where(fn (Builder $p) => $p->where('status', ArticleStatus::Published->value)->whereIn('audience', $audiences));
            if ($user->can('kb.write')) {
                $q->orWhere('created_by', $user->id);
            }
        });
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = request()->user();
        $query = static::query();
        if ($user instanceof User) {
            $query->visibleTo($user);
        }

        return $query->where($field ?? 'id', $value)->firstOrFail();
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /** @return BelongsTo<Ticket, $this> */
    public function sourceTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'source_ticket_id')->withoutGlobalScopes();
    }

    /** @return HasMany<KbArticleAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(KbArticleAttachment::class, 'article_id');
    }

    /** @return HasMany<KbArticleVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(KbArticleVote::class, 'article_id');
    }
}
