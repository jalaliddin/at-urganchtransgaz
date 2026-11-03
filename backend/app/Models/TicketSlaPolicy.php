<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Response/resolution targets in business minutes for a priority, optionally per category.
 *
 * @property int $id
 * @property TicketPriority $priority
 * @property int|null $category_id
 * @property int $response_minutes
 * @property int $resolution_minutes
 */
class TicketSlaPolicy extends Model
{
    use Auditable, HasUserStamps;

    protected $fillable = ['priority', 'category_id', 'response_minutes', 'resolution_minutes'];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }
}
