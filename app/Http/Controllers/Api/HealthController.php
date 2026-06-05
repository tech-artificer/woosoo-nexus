<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController
{
    /**
     * Check system health: database connectivity, Reverb, queue status
     *
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'app_version' => config('app.version', '1.0.0'),
            'services' => [],
        ];

        // Check primary MySQL connection
        try {
            DB::connection('mysql')->select('SELECT 1');
            $health['services']['mysql'] = ['status' => 'up'];
        } catch (\Exception $e) {
            $health['services']['mysql'] = ['status' => 'down', 'error' => $e->getMessage()];
            $health['status'] = 'degraded';
        }

        // Check POS (Krypton) connection
        try {
            DB::connection('pos')->select('SELECT 1');
            $health['services']['pos'] = ['status' => 'up'];
        } catch (\Exception $e) {
            $health['services']['pos'] = ['status' => 'down', 'error' => $e->getMessage()];
            $health['status'] = 'degraded';
        }

        // Queue status (check if jobs are backed up)
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $health['services']['queue'] = [
                'status' => $pendingJobs < 100 ? 'up' : 'overloaded',
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
            ];
            if ($pendingJobs >= 100) {
                $health['status'] = 'degraded';
            }
        } catch (\Exception $e) {
            $health['services']['queue'] = ['status' => 'unknown', 'error' => $e->getMessage()];
        }

        // Check broadcasting configuration integrity
        try {
            $broadcastingStatus = $this->checkBroadcastingIntegrity();
            $health['services']['broadcasting'] = $broadcastingStatus;
            if (!$broadcastingStatus['consistent']) {
                $health['status'] = 'degraded';
            }
        } catch (\Exception $e) {
            $health['services']['broadcasting'] = ['status' => 'unknown', 'error' => $e->getMessage()];
        }

        // Determine HTTP status code
        $httpStatus = match ($health['status']) {
            'ok' => 200,
            'degraded' => 503,
            default => 500,
        };

        return response()->json($health, $httpStatus);
    }

    /**
     * Check Reverb configuration consistency
     *
     * @return array
     */
    private function checkBroadcastingIntegrity(): array
    {
        $driver = config('broadcasting.default');

        if ($driver !== 'reverb') {
            return [
                'driver' => $driver,
                'consistent' => false,
            ];
        }

        $reverbApps = config('reverb.apps.apps');
        if (!is_array($reverbApps) || count($reverbApps) === 0) {
            return [
                'driver' => 'reverb',
                'consistent' => false,
            ];
        }

        $reverbApp = $reverbApps[0];
        $broadcastingReverb = config('broadcasting.connections.reverb');

        $reverbKey = trim((string) ($reverbApp['key'] ?? ''));
        $reverbSecret = trim((string) ($reverbApp['secret'] ?? ''));
        $reverbId = trim((string) ($reverbApp['app_id'] ?? ''));

        $broadcastKey = trim((string) ($broadcastingReverb['key'] ?? ''));
        $broadcastSecret = trim((string) ($broadcastingReverb['secret'] ?? ''));
        $broadcastId = trim((string) ($broadcastingReverb['app_id'] ?? ''));

        $consistent = $reverbKey === $broadcastKey
            && $reverbSecret === $broadcastSecret
            && $reverbId === $broadcastId
            && trim((string) env('REVERB_APP_KEY')) === $reverbKey
            && trim((string) env('REVERB_APP_ID')) === $reverbId
            && trim((string) env('REVERB_APP_SECRET')) === $reverbSecret;

        $host = trim((string) config('broadcasting.connections.reverb.options.host', ''));
        $port = (int) config('broadcasting.connections.reverb.options.port', 8080);
        $scheme = trim((string) config('broadcasting.connections.reverb.options.scheme', 'https'));

        // Create a fingerprint of the key (redacted)
        $keyFingerprint = $this->createKeyFingerprint($reverbKey);

        return [
            'driver' => $driver,
            'key_fingerprint' => $keyFingerprint,
            'host' => $host,
            'port' => $port,
            'scheme' => $scheme,
            'consistent' => $consistent,
        ];
    }

    /**
     * Create a redacted fingerprint of the key
     *
     * @param string $key
     * @return string
     */
    private function createKeyFingerprint(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return 'none';
        }

        $first4 = substr($key, 0, 4);
        $length = strlen($key);
        $shaPrefix = substr(hash('sha256', $key), 0, 8);

        return "{$first4}...({$length}b, sha256:{$shaPrefix})";
    }
}
