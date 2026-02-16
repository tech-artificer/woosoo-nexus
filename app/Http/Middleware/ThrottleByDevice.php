<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limit by device ID instead of IP to prevent X-Forwarded-For spoofing.
 *
 * For unauthenticated endpoints (e.g., /register), falls back to IP with fingerprinting.
 */
class ThrottleByDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, RateLimiter::remaining($key, $maxAttempts)),
        ]);
    }

    /**
     * Resolve the request signature for rate limiting.
     *
     * Prioritizes device ID from authenticated user, falls back to fingerprinting.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        // If authenticated as a device, use device ID (unspoofable)
        $device = $request->user();
        if ($device && isset($device->id)) {
            return 'device:' . $device->id;
        }

        // For unauthenticated requests (e.g., /register), use fingerprint
        // Combine multiple factors to prevent trivial bypass
        $fingerprint = implode('|', [
            $request->ip(),
            $request->header('User-Agent', 'unknown'),
            $request->path(),
        ]);

        return 'fingerprint:' . sha1($fingerprint);
    }
}
