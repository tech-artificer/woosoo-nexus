<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CheckStaleRelayHeartbeats;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CheckStaleRelayHeartbeatsTest extends TestCase
{
    public function test_job_has_explicit_timeout_and_single_retry_limit(): void
    {
        $job = new CheckStaleRelayHeartbeats;

        $this->assertSame(1, $job->tries);
        $this->assertSame(1, $job->maxExceptions);
        $this->assertSame(30, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertSame(100, $job->batchSize);
        $this->assertSame(1000, $job->maxRowsPerRun);
        $this->assertSame(10, $job->maxBatchesPerRun);
        $this->assertSame(25, $job->maxRuntimeSeconds);
        $this->assertSame(5000, $job->queryTimeoutMs);
    }

    public function test_job_uses_without_overlapping_middleware(): void
    {
        $job = new CheckStaleRelayHeartbeats;

        $middleware = $job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_failed_handler_logs_timeout_context(): void
    {
        $job = new CheckStaleRelayHeartbeats;

        $this->assertTrue(method_exists($job, 'failed'));

        Log::spy();
        $exception = new \RuntimeException('Job timed out');

        $job->failed($exception);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, '[Relay] Stale heartbeat job failed')
                    && isset($context['exception'])
                    && $context['exception'] === 'Job timed out';
            });
    }
}
