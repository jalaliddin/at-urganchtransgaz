<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free counters by key (e.g. "asset", "transfer:2026"). Call inside the
 * transaction that uses the number: the row stays locked until commit and a
 * rollback returns the number. One statement locks and increments, so parallel
 * callers queue instead of deadlocking.
 */
class NumberSequence
{
    public function next(string $key): int
    {
        $connection = DB::connection();
        $connection->statement(
            'INSERT INTO number_sequences (`key`, last_number) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)',
            [$key],
        );

        return (int) $connection->selectOne('SELECT LAST_INSERT_ID() AS n')->n;
    }
}
