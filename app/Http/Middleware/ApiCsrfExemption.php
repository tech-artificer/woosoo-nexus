<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

/**
 * Middleware to exempt API routes from CSRF validation.
 * 
 * The statefulApi() middleware applies Sanctum's CSRF protection to all
 * requests with cookies, including API routes. This middleware prevents
 * CSRF validation for stateless API endpoints that use Bearer token auth.
 */
class ApiCsrfExemption extends VerifyCsrfToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip CSRF validation entirely for API routes
        // This prevents 419 errors on API routes when cookies are present
        if ($request->is('api/*')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
    
    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request)
    {
        // Always exempt API routes
        if ($request->is('api/*')) {
            return true;
        }
        
        return parent::inExceptArray($request);
    }
}
