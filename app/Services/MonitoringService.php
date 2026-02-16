<?php

namespace App\Services;

use App\Models\BroadcastEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitoringService
{
    /**
     * Record system health metrics for monitoring/alerting
     */
    public static function recordMetric(string $metric, int|float $value, array $tags = []): void
    {
        try {
            $data = [
                'metric' => $metric,
                'value' => $value,
                'timestamp' => now()->getTimestamp(),
                'tags' => $tags,
            ];

            // Write to cache for fast access
            $cacheKey = "monitoring:metric:{$metric}";
            Cache::put($cacheKey, $data, now()->addMinutes(5));

            // Log structured data
            Log::info('Metric recorded', $data);
        } catch (\Exception $e) {
            Log::warning("Failed to record metric {$metric}: " . $e->getMessage());
        }
    }

    /**
     * Get current system metrics for /metrics endpoint
     */
    public static function getMetrics(): array
    {
        $metrics = [
            'timestamp' => now()->iso8601(),
            'uptime_seconds' => time() - (int)env('APP_START_TIME', time()),
        ];

        // Queue depth
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $metrics['queue'] = [
                'pending' => $pendingJobs,
                'failed' => $failedJobs,
                'threshold_exceeded' => $pendingJobs >= 100,
            ];
        } catch (\Exception $e) {
            $metrics['queue'] = ['error' => $e->getMessage()];
        }

        // Broadcast events (last 24 hours)
        try {
            $eventCount = BroadcastEvent::where('created_at', '>=', now()->subHours(24))->count();
            $metrics['broadcast_events_24h'] = $eventCount;
        } catch (\Exception $e) {
            $metrics['broadcast_events_24h'] = ['error' => $e->getMessage()];
        }

        // Database connections
        try {
            DB::connection('mysql')->select('SELECT 1');
            $metrics['mysql'] = 'ok';
        } catch (\Exception $e) {
            $metrics['mysql'] = 'down';
        }

        try {
            DB::connection('pos')->select('SELECT 1');
            $metrics['pos'] = 'ok';
        } catch (\Exception $e) {
            $metrics['pos'] = 'down';
        }

        return $metrics;
    }

    /**
     * Check for critical issues requiring alerts
     */
    public static function checkAlerts(): array
    {
        $alerts = [];

        // Queue threshold
        $pendingJobs = DB::table('jobs')->count();
        if ($pendingJobs >= 100) {
            $alerts[] = [
                'severity' => 'high',
                'message' => "Print queue overloaded: {$pendingJobs} pending jobs",
                'metric' => 'queue.depth',
            ];
        }

        // Broadcast events growth
        $recentEvents = BroadcastEvent::where('created_at', '>=', now()->subMinutes(5))->count();
        if ($recentEvents > 500) {
            $alerts[] = [
                'severity' => 'medium',
                'message' => "High broadcast event rate: {$recentEvents} events in last 5 minutes",
                'metric' => 'broadcast.rate',
            ];
        }

        // Database connectivity
        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Exception $e) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => 'MySQL connection failed: ' . $e->getMessage(),
                'metric' => 'database.mysql',
            ];
        }

        try {
            DB::connection('pos')->select('SELECT 1');
        } catch (\Exception $e) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => 'POS database connection failed: ' . $e->getMessage(),
                'metric' => 'database.pos',
            ];
        }

        return $alerts;
    }
}
