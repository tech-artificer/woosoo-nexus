<?php

namespace App\Http\Controllers\Api;

use App\Services\MonitoringService;
use Illuminate\Http\JsonResponse;

class MonitoringController
{
    /**
     * Get current system metrics (Prometheus/monitoring format)
     */
    public function metrics(): JsonResponse
    {
        $metrics = MonitoringService::getMetrics();
        $alerts = MonitoringService::checkAlerts();

        return response()->json([
            'status' => count($alerts) > 0 ? 'degraded' : 'ok',
            'metrics' => $metrics,
            'alerts' => $alerts,
            'timestamp' => now()->iso8601(),
        ]);
    }

    /**
     * Simple liveness probe for load balancer health checks
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'alive'], 200);
    }

    /**
     * Readiness probe - checks if app is ready to serve traffic
     */
    public function ready(): JsonResponse
    {
        try {
            // Quick health check
            $health = app(\App\Http\Controllers\Api\HealthController::class)->check();
            $data = json_decode($health->getContent(), true);
            
            $isReady = $data['status'] === 'ok' || $data['status'] === 'degraded';
            $statusCode = $isReady ? 200 : 503;

            return response()->json([
                'ready' => $isReady,
                'health' => $data,
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
