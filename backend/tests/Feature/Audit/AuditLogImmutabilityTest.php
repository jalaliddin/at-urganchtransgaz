<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(): AuditLog
    {
        return app(AuditLogger::class)->record(AuditEvent::LoginFailed, userLabel: 'a.karimov');
    }

    public function test_logger_records_standalone_events(): void
    {
        $log = $this->makeLog();

        $this->assertSame('login_failed', $log->fresh()->event->value);
        $this->assertSame('a.karimov', $log->user_label);
        $this->assertNotNull($log->created_at);
    }

    public function test_update_throws(): void
    {
        $log = $this->makeLog();

        $this->expectException(LogicException::class);
        $log->update(['user_label' => 'hacker']);
    }

    public function test_delete_throws(): void
    {
        $log = $this->makeLog();

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
