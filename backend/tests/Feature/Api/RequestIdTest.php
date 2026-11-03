<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Str;
use Tests\TestCase;

class RequestIdTest extends TestCase
{
    public function test_generates_uuid_request_id(): void
    {
        $id = $this->getJson('/api/v1/health')->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid($id));
    }

    public function test_echoes_valid_incoming_uuid(): void
    {
        $incoming = (string) Str::uuid();

        $this->getJson('/api/v1/health', ['X-Request-Id' => $incoming])
            ->assertHeader('X-Request-Id', $incoming);
    }

    public function test_replaces_invalid_incoming_value(): void
    {
        $id = $this->getJson('/api/v1/health', ['X-Request-Id' => '<script>alert(1)</script>'])
            ->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid($id));
    }
}
