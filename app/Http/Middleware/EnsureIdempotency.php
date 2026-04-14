<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('X-Idempotency-Key', ''));
        if ($key === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Idempotency-Key header',
            ], 400);
        }

        $actorId = (int) ($request->user()?->id ?? 0);
        $scope = implode(':', [
            strtoupper($request->method()),
            trim($request->path(), '/'),
            (string) $actorId,
            $key,
        ]);
        $cacheKey = 'idempotency:' . sha1($scope);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return response()->json(
                $cached['body'] ?? ['success' => true],
                (int) ($cached['status'] ?? 200)
            );
        }

        /** @var Response $response */
        $response = $next($request);

        // Cache successful outcomes and conflict outcomes to dedupe replays.
        $status = $response->getStatusCode();
        if (($status >= 200 && $status < 300) || $status === 409) {
            $body = null;
            if ($response instanceof JsonResponse) {
                $body = $response->getData(true);
            }

            if (is_array($body)) {
                Cache::put($cacheKey, [
                    'status' => $status,
                    'body' => $body,
                ], now()->addHours(2));
            }
        }

        return $response;
    }
}
