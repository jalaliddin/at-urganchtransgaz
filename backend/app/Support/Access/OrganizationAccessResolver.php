<?php

namespace App\Support\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Computes a user's AccessScope from user_organization_access rows. Bound as a
 * scoped singleton, so results are memoised per request/job. Uses the query
 * builder (not Eloquent) so organization global scopes can never recurse here.
 */
class OrganizationAccessResolver
{
    /** @var array<int, AccessScope> */
    private array $cache = [];

    public function resolve(User $user): AccessScope
    {
        return $this->cache[$user->id] ??= $this->compute($user);
    }

    public function forget(User $user): void
    {
        unset($this->cache[$user->id]);
    }

    /** Drop every cached scope (e.g. after the organization tree changed). */
    public function flush(): void
    {
        $this->cache = [];
    }

    private function compute(User $user): AccessScope
    {
        if ($user->isSuperAdmin()) {
            return AccessScope::unrestricted();
        }

        $rows = DB::table('user_organization_access as a')
            ->join('organizations as o', 'o.id', '=', 'a.organization_id')
            ->whereNull('o.deleted_at')
            ->where('a.user_id', $user->id)
            ->get(['o.id', 'o.path', 'a.include_descendants']);

        $ids = [];
        foreach ($rows as $row) {
            $ids[(int) $row->id] = true;

            if ($row->include_descendants) {
                $descendants = DB::table('organizations')
                    ->where('path', 'like', $row->path.'%')
                    ->whereNull('deleted_at')
                    ->pluck('id');

                foreach ($descendants as $id) {
                    $ids[(int) $id] = true;
                }
            }
        }

        return new AccessScope(false, array_keys($ids));
    }
}
