<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class RequestId
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('X-Request-Id') ?: $request->header('x-request-id');

        $requestId = $header ?: (string) Str::uuid();

        // Attach to the request and logging context
        $request->attributes->set('request_id', $requestId);

        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);

        // Ensure header is present on response
        if (method_exists($response, 'header')) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
    }
}
