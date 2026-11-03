<?php

namespace Tests\Feature\Api;

use App\Exceptions\DomainConflictException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Spatie\QueryBuilder\QueryBuilder;
use Tests\TestCase;

class ErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/__test')->group(function (): void {
            Route::post('validate', fn (Request $request) => $request->validate(['name' => 'required']));
            Route::get('boom', fn () => throw new RuntimeException('SQLSTATE[42S02] secret table'));
            Route::get('conflict', fn () => throw new DomainConflictException('Bu tashkilotda xodimlar bor.'));
            Route::get('missing', fn () => throw (new ModelNotFoundException)->setModel(User::class, [99]));
            Route::get('forbidden', fn () => throw new AuthorizationException);
            Route::get('private', fn () => 'ok')->middleware('auth:sanctum');
            Route::get('users', fn () => QueryBuilder::for(User::class)->allowedSorts('id')->allowedFilters('name')->get());
            Route::get('limited', fn () => 'ok')->middleware('throttle:1,1');
        });
    }

    public function test_unknown_route_returns_not_found_envelope(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found')
            ->assertJsonStructure(['message', 'code']);
    }

    public function test_validation_error_envelope_in_uzbek(): void
    {
        $this->postJson('/api/v1/__test/validate', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('message', __('messages.validation_failed', [], 'uz'))
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_server_error_hides_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/__test/boom')
            ->assertStatus(500)
            ->assertJsonPath('code', 'server_error')
            ->assertJsonStructure(['message', 'code', 'request_id']);

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function test_domain_conflict_maps_to_409(): void
    {
        $this->getJson('/api/v1/__test/conflict')
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict')
            ->assertJsonPath('message', 'Bu tashkilotda xodimlar bor.');
    }

    public function test_model_not_found_hides_model_class(): void
    {
        $response = $this->getJson('/api/v1/__test/missing')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->assertStringNotContainsString('App\\\\Models', $response->getContent());
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }

    public function test_unauthenticated_envelope(): void
    {
        $this->getJson('/api/v1/__test/private')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_forbidden_envelope(): void
    {
        $this->getJson('/api/v1/__test/forbidden')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_invalid_sort_returns_400(): void
    {
        $this->getJson('/api/v1/__test/users?sort=password')
            ->assertStatus(400)
            ->assertJsonPath('code', 'bad_request');
    }

    public function test_unknown_filter_returns_400(): void
    {
        $this->getJson('/api/v1/__test/users?filter[password]=x')
            ->assertStatus(400)
            ->assertJsonPath('code', 'bad_request');
    }

    public function test_throttle_envelope(): void
    {
        $this->getJson('/api/v1/__test/limited')->assertOk();

        $this->getJson('/api/v1/__test/limited')
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }
}
