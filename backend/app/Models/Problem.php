<?php

namespace App\Models;

use App\Enums\ProblemPriority;
use App\Enums\ProblemStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUserStamps;
use App\Models\Concerns\ScopedToUserOrganizations;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A problem behind one or more incidents (design R1).
 *
 * @property int $id
 * @property string $number
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property int|null $category_id
 * @property ProblemPriority $priority
 * @property ProblemStatus $status
 * @property int|null $assignee_id
 * @property string|null $root_cause
 * @property string|null $workaround
 * @property string|null $resolution
 * @property string|null $close_reason
 * @property int|null $kb_article_id
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Organization $organization
 * @property-read TicketCategory|null $category
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read KbArticle|null $article
 */
class Problem extends Model
{
    use Auditable, BelongsToOrganization, HasUserStamps, ScopedToUserOrganizations;

    protected $fillable = ['organization_id', 'title', 'description', 'category_id', 'priority', 'root_cause', 'workaround', 'resolution', 'kb_article_id'];

    protected function casts(): array
    {
        return [
            'priority' => ProblemPriority::class,
            'status' => ProblemStatus::class,
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withoutGlobalScope(OrganizationScope::class);
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /** @return BelongsTo<KbArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }

    /** @return BelongsToMany<Ticket, $this> */
    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'problem_tickets', 'problem_id', 'ticket_id')->withoutGlobalScopes();
    }

    /** @return HasMany<ProblemHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(ProblemHistory::class);
    }
}
