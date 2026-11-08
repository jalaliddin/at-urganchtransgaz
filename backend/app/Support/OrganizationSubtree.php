<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class OrganizationSubtree
{
    /**
     * Ids of $organizationId and its descendants (empty when it does not exist).
     *
     * @return list<int>
     */
    public static function ids(int $organizationId): array
    {
        $path = DB::table('organizations')->where('id', $organizationId)->whereNull('deleted_at')->value('path');
        if (! is_string($path) || $path === '') {
            return [];
        }

        return DB::table('organizations')->where('path', 'like', $path.'%')->whereNull('deleted_at')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
