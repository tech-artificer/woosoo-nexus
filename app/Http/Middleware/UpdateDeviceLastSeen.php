<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class UpdateDeviceLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/printer/heartbeat')) {
            return $response;
        }

        $device = $request->user();
        if (! $device instanceof Device) {
            return $response;
        }

        $ttl = max(1, (int) config('devices.last_seen_write_throttle_seconds', 30));
        $seenRecently = $device->last_seen_at?->gt(now()->subSeconds($ttl)) ?? false;

        if ($seenRecently) {
            return $response;
        }

        try {
            $device->forceFill(['last_seen_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('[DeviceLastSeen] Failed to update device last_seen_at', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
