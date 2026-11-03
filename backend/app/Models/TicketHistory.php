<?php

namespace App\Models;

use App\Enums\TicketHistoryAction;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only timeline entry of a ticket.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property TicketHistoryAction $action
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $note
 * @property bool $is_internal
 */
class TicketHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'user_id', 'action', 'old_values', 'new_values', 'note', 'is_internal'];

    protected function casts(): array
    {
        return [
            'action' => TicketHistoryAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'is_internal' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(OrganizationScope::class);
    }
}
