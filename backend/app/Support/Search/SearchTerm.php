<?php

namespace App\Support\Search;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Case-insensitive "contains" search that treats LIKE wildcards literally and
 * matches Uzbek apostrophe variants (ʻ ʼ ‘ ’ `) interchangeably with '.
 */
final class SearchTerm
{
    /** Apostrophe look-alikes used in Uzbek Latin text (oʻ, gʻ, ʼ). */
    public const APOSTROPHES = ['ʻ', 'ʼ', '‘', '’', '`'];

    /**
     * @template TBuilder of BuilderContract
     *
     * @param  TBuilder  $query
     * @param  list<string>  $columns  trusted column names (never user input)
     * @return TBuilder
     */
    public static function whereContains(BuilderContract $query, array $columns, string $term): BuilderContract
    {
        $term = trim($term);
        if ($term === '' || $columns === []) {
            return $query;
        }

        $pattern = '%'.self::escapeLike(self::normalize($term)).'%';

        $query->where(function ($q) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $q->orWhereRaw(self::normalizedColumn($column)." LIKE ? ESCAPE '!'", [$pattern]);
            }
        });

        return $query;
    }

    public static function normalize(string $value): string
    {
        return str_replace(self::APOSTROPHES, "'", $value);
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private static function normalizedColumn(string $column): string
    {
        $sql = self::wrap($column);
        foreach (self::APOSTROPHES as $char) {
            $sql = "REPLACE({$sql}, '{$char}', '''')";
        }

        return $sql;
    }

    private static function wrap(string $column): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException("Invalid search column [{$column}].");
        }

        return implode('.', array_map(fn (string $part) => "`{$part}`", explode('.', $column)));
    }
}
