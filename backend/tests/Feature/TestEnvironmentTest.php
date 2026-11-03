<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestEnvironmentTest extends TestCase
{
    public function test_tests_run_against_the_dedicated_test_database(): void
    {
        $this->assertSame('at_urtg_test', DB::connection()->getDatabaseName());
        $this->assertSame('testing', app()->environment());
    }

    public function test_health_route_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
