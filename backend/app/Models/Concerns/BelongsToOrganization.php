<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Marks a model as owned by an organization: registers OrganizationScope (tenant
 * isolation) and provides the subtree filter used by list endpoints.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    /** Column holding the owning organization id. */
    public static function organizationColumn(): string
    {
        return 'organization_id';
    }

    /**
     * Limit to records of $organizationId and its descendants.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithinOrganizationSubtree(Builder $query, int $organizationId): void
    {
        $path = DB::table('organizations')->where('id', $organizationId)->whereNull('deleted_at')->value('path');

        if (! is_string($path) || $path === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn(
            $this->qualifyColumn(static::organizationColumn()),
            DB::table('organizations')->select('id')->where('path', 'like', $path.'%')->whereNull('deleted_at'),
        );
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithoutOrganizationScope(Builder $query): void
    {
        $query->withoutGlobalScope(OrganizationScope::class);
    }
}
