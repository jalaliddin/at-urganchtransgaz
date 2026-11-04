<?php

namespace App\Support\QueryFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Sorts a string column by a fixed meaning order (e.g. priority low → critical)
 * instead of alphabetically.
 */
class EnumOrderSort implements Sort
{
    /** @param  list<string>  $order  ascending order */
    public function __construct(private readonly array $order) {}

    /** @param  Builder<Model>  $query */
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $placeholders = implode(',', array_fill(0, count($this->order), '?'));
        $query->orderByRaw(
            'FIELD('.$query->qualifyColumn($property).", {$placeholders}) ".($descending ? 'desc' : 'asc'),
            $this->order,
        );
    }
}
