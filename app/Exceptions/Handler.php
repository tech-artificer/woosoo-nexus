<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Inertia;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        //
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof AuthorizationException) {
            // Preserve JSON responses for API consumers
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage() ?: 'This action is unauthorized.'], 403);
            }

            // Render friendly Inertia 403 page for web requests
            return Inertia::render('errors/403', [
                'title' => 'Forbidden',
                'description' => 'You do not have permission to access this page'
            ])->toResponse($request)->setStatusCode(403);
        }

        return parent::render($request, $exception);
    }
}
