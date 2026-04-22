<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestId;
use Illuminate\Routing\Middleware\SubstituteBindings;
use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
// use App\Http\Middleware\CheckSessionIsOpened;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // ->withBroadcasting(
    //     __DIR__.'/../routes/channels.php',
    //     ['prefix' => 'api'],
    // )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ Global middleware (runs on all routes)
        // TrustProxies must be first — it normalises X-Forwarded-Proto before
        // any URL generation (assets, redirects, CSRF) reads the request scheme.
        $middleware->prepend([
            TrustProxies::class,
            HandleCors::class,
            RequestId::class,
        ]);

        $middleware->api(append: [
            ForceJsonResponse::class,
            // CheckSessionIsOpened::class,
            SubstituteBindings::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'throttle.device' => \App\Http\Middleware\ThrottleByDevice::class,
        ]);

        // Stateful API (cookie-based SPA auth) is intentionally disabled.
        // Device auth uses Bearer tokens only; CSRF protection via cookies is not needed.
        // $middleware->statefulApi();
    })
    
    ->withExceptions(function (Exceptions $exceptions) {
       $exceptions->render(function (QueryException $exception, Request $request) {

            if ($request->is('api/*')) {
                if ($exception->errorInfo[1] == 1062) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code'    => 'DUPLICATE_ENTRY',
                            'message' => 'A duplicate entry was detected.',
                            'details' => [],
                        ],
                        'meta' => [
                            'request_id' => $request->attributes->get('request_id', ''),
                            'timestamp'  => now()->toIso8601String(),
                        ],
                    ], 409);
                }
            }
        });

        // Structured JSON error envelope for all API 4xx/5xx responses.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null; // Let Inertia handle web routes
            }

            // Let the default handler build its response first, then re-wrap it
            $defaultHandler = new \App\Exceptions\Handler(app());
            $response = $defaultHandler->render($request, $e);

            $status = $response->getStatusCode();

            // Only envelope error codes — pass 2xx/3xx through unchanged
            if ($status < 400) {
                return null;
            }

            $original = json_decode($response->getContent(), true) ?? [];

            // Already structured with our envelope — pass through
            if (isset($original['error']['code'])) {
                return $response;
            }

            $code    = \App\Support\ApiErrorCode::fromException($e, $status);
            $message = $original['message'] ?? ($e->getMessage() ?: 'An unexpected error occurred.');

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => $code,
                    'message' => $message,
                    'details' => $original['errors'] ?? [],
                ],
                'meta' => [
                    'request_id' => $request->attributes->get('request_id', ''),
                    'timestamp'  => now()->toIso8601String(),
                ],
            ], $status);
        });
    })->create();
