<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('api/v1/__test/locale', fn () => ['locale' => app()->getLocale()]);
    }

    public function test_defaults_to_uzbek(): void
    {
        $this->getJson('/api/v1/__test/locale')->assertJsonPath('locale', 'uz');
    }

    public function test_accept_language_ru_selects_russian(): void
    {
        $this->getJson('/api/v1/__test/locale', ['Accept-Language' => 'ru-RU,ru;q=0.9'])
            ->assertJsonPath('locale', 'ru');
    }

    public function test_unsupported_language_falls_back_to_uzbek(): void
    {
        $this->getJson('/api/v1/__test/locale', ['Accept-Language' => 'de-DE'])
            ->assertJsonPath('locale', 'uz');
    }
}
