<?php

namespace Tests\Feature\Helpdesk;

use App\Services\Helpdesk\TicketNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Two processes take ticket numbers at the same time on separate MySQL
 * connections: no deadlock, no duplicate, no gap.
 */
class TicketNumberConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 2091;

    private const PER_WORKER = 40;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required');
        }
        foreach (['aux', 'aux_a', 'aux_b'] as $name) {
            config(["database.connections.{$name}" => config('database.connections.'.config('database.default'))]);
        }
        DB::connection('aux')->table('ticket_counters')->where('year', self::YEAR)->delete();
    }

    protected function tearDown(): void
    {
        DB::connection('aux')->table('ticket_counters')->where('year', self::YEAR)->delete();
        DB::connection('aux')->disconnect();
        parent::tearDown();
    }

    public function test_parallel_workers_get_unique_consecutive_numbers(): void
    {
        $dir = sys_get_temp_dir().'/hd-numbers-'.getmypid();
        @mkdir($dir);
        $pids = [];
        foreach (['aux_a', 'aux_b'] as $connection) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $this->work($connection, "{$dir}/{$connection}.txt");
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $numbers = [];
        foreach (['aux_a', 'aux_b'] as $connection) {
            $lines = file("{$dir}/{$connection}.txt", FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $line) {
                $this->assertStringStartsWith('HD-', $line, "worker {$connection} failed: {$line}");
                $numbers[] = $line;
            }
            @unlink("{$dir}/{$connection}.txt");
        }
        @rmdir($dir);

        sort($numbers);
        $expected = array_map(fn (int $i) => sprintf('HD-%d-%06d', self::YEAR, $i), range(1, 2 * self::PER_WORKER));
        $this->assertSame($expected, $numbers);
    }

    /** Child process: never returns; exits without running destructors on the parent's shared connection. */
    private function work(string $connection, string $file): never
    {
        $handle = fopen($file, 'w');
        try {
            DB::setDefaultConnection($connection);
            $generator = app(TicketNumberGenerator::class);
            $at = CarbonImmutable::parse(self::YEAR.'-06-01 10:00', 'UTC');
            for ($i = 0; $i < self::PER_WORKER; $i++) {
                $number = DB::connection($connection)->transaction(fn () => $generator->next($at));
                fwrite($handle, $number."\n");
            }
        } catch (Throwable $e) {
            fwrite($handle, 'ERROR '.$e->getMessage()."\n");
        }
        fclose($handle);
        posix_kill(getmypid(), SIGKILL);
        exit(0);
    }
}
