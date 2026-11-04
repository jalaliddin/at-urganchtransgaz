<?php

namespace App\Services\Exports;

use App\Models\User;

/** A report that can be exported as a table (XLSX / PDF). */
interface ReportDefinition
{
    public function permission(): string;

    /** @return list<string> filter names accepted by this report */
    public function filterNames(): array;

    public function title(string $locale): string;

    /** @return list<string> */
    public function columns(string $locale): array;

    /**
     * Rows for $user (scope applied explicitly — jobs run without an authenticated user).
     *
     * @param  array<string, mixed>  $filters
     * @return iterable<list<string|int|float|null>>
     */
    public function rows(User $user, array $filters, string $locale): iterable;
}
