<?php

namespace App\Support\Search;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Http\Request;

/**
 * Case-insensitive "contains" search: every word of the term must appear in one of
 * the columns. LIKE wildcards are literal and Uzbek apostrophe variants (ʻ ʼ ‘ ’ `)
 * match ' interchangeably.
 */
final class SearchTerm
{
    /** Apostrophe look-alikes used in Uzbek Latin text (oʻ, gʻ, ʼ). */
    public const APOSTROPHES = ['ʻ', 'ʼ', '‘', '’', '`'];

    private const MAX_WORDS = 5;

    /**
     * @template TBuilder of BuilderContract
     *
     * @param  TBuilder  $query
     * @param  list<string>  $columns  trusted column names (never user input)
     * @return TBuilder
     */
    public static function whereContains(BuilderContract $query, array $columns, string $term): BuilderContract
    {
        $words = array_slice(preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, self::MAX_WORDS);
        if ($words === [] || $columns === []) {
            return $query;
        }

        // Every word must appear in at least one of the columns (any order).
        foreach ($words as $word) {
            $pattern = '%'.self::escapeLike(self::normalize($word)).'%';
            $query->where(function ($q) use ($columns, $pattern): void {
                foreach ($columns as $column) {
                    $q->orWhereRaw(self::normalizedColumn($column)." LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        return $query;
    }

    /** The ?search query value; anything but a string (e.g. search[]=x) counts as empty. */
    public static function fromRequest(Request $request, string $key = 'search'): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
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
