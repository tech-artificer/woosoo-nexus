<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Middleware to exempt only stateless device-bootstrap API endpoints from CSRF.
 *
 * Keep CSRF protection enabled for all other routes, including session-authenticated
 * Sanctum endpoints under /api that rely on browser cookies.
 */
class ApiCsrfExemption extends ValidateCsrfToken
{
    /**
     * Stateless bootstrap endpoints used by devices before Bearer auth exists.
     */
    protected $except = [
        'api/devices/register',
        'api/devices/login',
        'api/device/lookup-by-ip',
    ];
}
