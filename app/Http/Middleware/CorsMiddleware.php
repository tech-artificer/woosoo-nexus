<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Define your allowed origins.
        // For local development, you might use your frontend's URL or even '*' (NOT recommended for production).
        $allowedOrigins = [
            'http://localhost:3000', // Example: your frontend running on port 3000
            'http://127.0.0.1:8000', // Example: another local dev server
        ];

        // Or, for extreme temporary local dev, allow all (DANGER: NEVER IN PRODUCTION)
        // $allowedOrigin = '*';


        // Get the Origin header from the request
        $origin = $request->header('Origin');

        // Determine if the origin is allowed
        if ($origin && (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins))) {
            $allowedOrigin = $origin; // Use the specific origin
        } else if (in_array('*', $allowedOrigins)) {
            $allowedOrigin = '*'; // Fallback to * if configured
        } else {
            $allowedOrigin = null; // No allowed origin, no CORS headers will be sent
        }

        // Handle preflight OPTIONS requests first
        if ($request->isMethod('OPTIONS')) {
            $response = new Response(); // Create a new empty response for OPTIONS
            if ($allowedOrigin) {
                $response->header('Access-Control-Allow-Origin', $allowedOrigin);
                $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS'); // All common methods
                $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With'); // Common headers
                $response->header('Access-Control-Max-Age', '86400'); // Cache preflight for 24 hours
                $response->header('Access-Control-Allow-Credentials', 'true'); // Only if needed for cookies/auth tokens
            }
            return $response;
        }

        // For actual requests (GET, POST, etc.)
        $response = $next($request); // Process the request and get the response

        // Add CORS headers to the actual response if origin is allowed
        if ($allowedOrigin) {
            $response->header('Access-Control-Allow-Origin', $allowedOrigin);
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Allow-Credentials', 'true'); // Only if needed for cookies/auth tokens
        }

        return $response;
    }
}
