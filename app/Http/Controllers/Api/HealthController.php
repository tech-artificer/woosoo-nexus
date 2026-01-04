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
            'timestamp' => now()->iso8601(),
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

        // Determine HTTP status code
        $httpStatus = match ($health['status']) {
            'ok' => 200,
            'degraded' => 503,
            default => 500,
        };

        return response()->json($health, $httpStatus);
    }
}
