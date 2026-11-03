<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\Search\SearchTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTermTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function search(string $term): array
    {
        return SearchTerm::whereContains(User::query(), ['name'], $term)
            ->orderBy('name')->pluck('name')->all();
    }

    public function test_apostrophe_variants_match_each_other(): void
    {
        User::factory()->create(['name' => 'Gʻulomov Aziz']);
        User::factory()->create(['name' => "O'rozov Bekzod"]);
        User::factory()->create(['name' => 'Karimov Jasur']);

        $this->assertSame(['Gʻulomov Aziz'], $this->search("G'ulomov"));
        $this->assertSame(['Gʻulomov Aziz'], $this->search('G‘ulomov'));
        $this->assertSame(["O'rozov Bekzod"], $this->search('Oʻroz'));
        $this->assertSame(["O'rozov Bekzod"], $this->search('Oʼroz'));
    }

    public function test_wildcards_are_literal(): void
    {
        User::factory()->create(['name' => '100% ok']);
        User::factory()->create(['name' => '1000 ok']);

        $this->assertSame(['100% ok'], $this->search('100%'));
        $this->assertSame([], $this->search('_'));
        $this->assertSame([], $this->search('\\'));
    }

    public function test_matches_any_of_several_columns(): void
    {
        User::factory()->create(['name' => 'Aziz', 'email' => 'boss@urtg.uz']);
        User::factory()->create(['name' => 'Boss Ali', 'email' => 'ali@urtg.uz']);

        $names = SearchTerm::whereContains(User::query(), ['name', 'email'], 'boss')
            ->orderBy('name')->pluck('name')->all();

        $this->assertSame(['Aziz', 'Boss Ali'], $names);
    }

    public function test_every_word_must_match_some_column_in_any_order(): void
    {
        User::factory()->create(['name' => 'Karimov Aziz', 'email' => 'aziz@urtg.uz']);
        User::factory()->create(['name' => 'Karimov Bekzod', 'email' => 'bek@urtg.uz']);

        $names = SearchTerm::whereContains(User::query(), ['name', 'email'], '  aziz   karimov ')
            ->pluck('name')->all();

        $this->assertSame(['Karimov Aziz'], $names);
    }
}
