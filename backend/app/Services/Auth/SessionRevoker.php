<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Ends a user's sessions (database session driver) and API tokens.
 */
class SessionRevoker
{
    public function revokeAll(User $user): void
    {
        $this->sessions()->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
    }

    public function revokeOthers(User $user, ?string $currentSessionId): void
    {
        $this->sessions()
            ->where('user_id', $user->id)
            ->when($currentSessionId !== null, fn ($q) => $q->where('id', '!=', $currentSessionId))
            ->delete();
    }

    private function sessions(): Builder
    {
        return DB::table((string) config('session.table', 'sessions'));
    }
}
