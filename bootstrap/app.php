<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Routing\Middleware\SubstituteBindings;
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
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['auth:device']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ Global middleware (runs on all routes)
        $middleware->prepend([
            HandleCors::class,
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

        $middleware->statefulApi();
    })
    
    ->withExceptions(function (Exceptions $exceptions) {
       $exceptions->render(function (QueryException $exception, Request $request) {

            if( $request->is('api/*') ) {

                if ($exception->errorInfo[1] == 1062) {
                    return response()->json([
                        'message' => 'Duplicate Entry Detected.',
                    ], 409);
                }

                //  return response()->json([
                //     'success' => false,
                //     'message' => 'Error Occurred.',
                // ]);
            }
           
           

            // // Not a duplicate entry? Let Laravel handle it.
            // throw $exception;
        });
    })->create();
