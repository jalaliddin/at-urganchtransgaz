<?php

namespace Tests\Feature\Docs;

use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    public function test_docs_are_open_in_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->get('/docs/api')->assertOk();
        $this->get('/docs/api.json')->assertOk()->assertJsonPath('info.title', 'AT.urtg.uz API');
    }

    public function test_docs_require_super_admin_elsewhere(): void
    {
        $this->get('/docs/api')->assertForbidden();

        $this->seedRbac();
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));
        $this->get('/docs/api.json')->assertOk();
    }
}
