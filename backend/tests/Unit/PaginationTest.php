<?php

namespace Tests\Unit;

use App\Support\Pagination;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase
{
    /** @return array<string, array{array<string, string>, int}> */
    public static function cases(): array
    {
        return [
            'default' => [[], 25],
            'too large is clamped' => [['per_page' => '1000'], 100],
            'zero becomes one' => [['per_page' => '0'], 1],
            'negative becomes one' => [['per_page' => '-5'], 1],
            'non numeric falls back' => [['per_page' => 'abc'], 25],
            'valid value' => [['per_page' => '40'], 40],
        ];
    }

    /** @param array<string, string> $query */
    #[DataProvider('cases')]
    public function test_per_page(array $query, int $expected): void
    {
        $this->assertSame($expected, Pagination::perPage(Request::create('/x', 'GET', $query)));
    }
}
