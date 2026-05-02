<?php

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckStaleRelayHeartbeats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Fail fast on timeout instead of allowing repeated re-queues.
     */
    public int $tries = 1;

    public int $maxExceptions = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    /**
     * Defensive controls against runaway scans.
     */
    public int $batchSize = 100;

    public int $maxRowsPerRun = 1000;

    public int $maxBatchesPerRun = 10;

    public int $maxRuntimeSeconds = 25;

    public int $queryTimeoutMs = 5000;

    /**
     * Ensure only one stale-heartbeat scan runs at a time even if multiple workers exist.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('check-stale-relay-heartbeats'))
                ->expireAfter(120)
                ->dontRelease(),
        ];
    }

    public function handle(): void
    {
        $staleBefore = now()->subMinutes(5);
        $scanTime = now();
        $startedAt = now();
        $lastId = 0;
        $processed = 0;
        $batches = 0;

        $this->applyQueryTimeoutGuard();

        // Only query printer_relay devices that have ever sent a heartbeat.
        // whereNotNull prevents false positives for newly registered devices.
        while ($processed < $this->maxRowsPerRun && $batches < $this->maxBatchesPerRun) {
            if ($startedAt->diffInSeconds(now()) >= $this->maxRuntimeSeconds) {
                Log::warning('[Relay] Stale heartbeat scan stopped by runtime guard', [
                    'processed' => $processed,
                    'batches' => $batches,
                    'max_runtime_seconds' => $this->maxRuntimeSeconds,
                ]);
                break;
            }

            $remaining = $this->maxRowsPerRun - $processed;
            $limit = min($this->batchSize, $remaining);

            $staleRelays = Device::query()
                ->where('id', '>', $lastId)
                ->where('type', 'printer_relay')
                ->whereNotNull('last_heartbeat_at')
                ->where('last_heartbeat_at', '<', $staleBefore)
                ->select(['id', 'name', 'last_heartbeat_at'])
                ->orderBy('id')
                ->limit($limit)
                ->get();

            if ($staleRelays->isEmpty()) {
                break;
            }

            foreach ($staleRelays as $relay) {
                Log::warning('[Relay] Stale heartbeat detected', [
                    'device_id' => $relay->id,
                    'device_name' => $relay->name,
                    'last_seen' => $relay->last_heartbeat_at?->toIso8601String(),
                    'minutes_silent' => $relay->last_heartbeat_at
                        ? $scanTime->diffInMinutes($relay->last_heartbeat_at)
                        : null,
                ]);
                // Future hook: broadcast admin alert event here
            }

            $processed += $staleRelays->count();
            $batches++;
            $lastId = (int) $staleRelays->last()->id;
        }

        Log::info('[Relay] Stale heartbeat scan completed', [
            'processed' => $processed,
            'batches' => $batches,
            'max_rows_per_run' => $this->maxRowsPerRun,
            'max_batches_per_run' => $this->maxBatchesPerRun,
            'runtime_seconds' => $startedAt->diffInSeconds(now()),
        ]);
    }

    private function applyQueryTimeoutGuard(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        // Applies only to SQL engines that support per-session statement timeouts.
        if ($driver === 'mysql') {
            $connection->statement("SET SESSION MAX_EXECUTION_TIME={$this->queryTimeoutMs}");

            return;
        }

        if ($driver === 'pgsql') {
            $connection->statement("SET statement_timeout TO {$this->queryTimeoutMs}");
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[Relay] Stale heartbeat job failed', [
            'job' => self::class,
            'attempts' => $this->attempts(),
            'timeout' => $this->timeout,
            'exception' => $exception->getMessage(),
        ]);
    }
}
