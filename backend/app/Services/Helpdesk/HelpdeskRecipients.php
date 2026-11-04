<?php

namespace App\Services\Helpdesk;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds active users holding a Helpdesk permission who can access an
 * organization (dispatchers, assignable handlers). Bypasses the caller's scope:
 * recipients are filtered by their own access instead.
 */
class HelpdeskRecipients
{
    /** @return Collection<int, User> */
    public function withPermission(string $permission, int $organizationId): Collection
    {
        $roleIds = DB::table('role_has_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('p.name', $permission)
            ->pluck('rp.role_id');

        return User::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $roleIds))
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->canAccessOrganization($organizationId))
            ->values();
    }

    /** @return Collection<int, User> users who triage new tickets of the ticket's organization */
    public function dispatchers(Ticket $ticket): Collection
    {
        return $this->withPermission('helpdesk.assign', $ticket->organization_id);
    }
}
