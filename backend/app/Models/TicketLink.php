<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Undirected link between two tickets (stored once).
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $linked_ticket_id
 * @property string $type relates|duplicates
 */
class TicketLink extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['relates', 'duplicates'];

    protected $fillable = ['ticket_id', 'linked_ticket_id', 'type', 'created_by'];

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<Ticket, $this> */
    public function linkedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'linked_ticket_id')->withoutGlobalScopes();
    }

    public function otherId(int $ticketId): int
    {
        return $this->ticket_id === $ticketId ? $this->linked_ticket_id : $this->ticket_id;
    }
}
