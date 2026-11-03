<?php

namespace App\Support\QueryFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * filter[organization]=<id>: records of that organization and its descendants.
 * Combined with the global organization scope this is always an intersection,
 * so an inaccessible id simply yields no rows.
 *
 * @implements Filter<Model>
 */
class OrganizationSubtreeFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $id = filter_var(is_array($value) ? reset($value) : $value, FILTER_VALIDATE_INT);

        if ($id === false) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->withinOrganizationSubtree($id); // @phpstan-ignore method.notFound (BelongsToOrganization scope)
    }
}
