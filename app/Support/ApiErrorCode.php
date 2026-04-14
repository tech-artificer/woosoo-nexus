<?php

namespace App\Support;

use App\Enums\ApiErrorCode as ErrorCode;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Legacy shim — kept for backwards compatibility with bootstrap/app.php.
 * All error codes are now defined in App\Enums\ApiErrorCode.
 *
 * @see \App\Enums\ApiErrorCode
 */
class ApiErrorCode
{
    /**
     * Derive a machine-readable error code string from an exception and HTTP status.
     */
    public static function fromException(Throwable $e, int $status): string
    {
        return match (true) {
            $e instanceof ValidationException           => ErrorCode::VALIDATION_ERROR->value,
            $e instanceof AuthenticationException       => ErrorCode::UNAUTHENTICATED->value,
            $e instanceof AuthorizationException        => ErrorCode::FORBIDDEN->value,
            $e instanceof NotFoundHttpException         => ErrorCode::NOT_FOUND->value,
            $e instanceof MethodNotAllowedHttpException => ErrorCode::METHOD_NOT_ALLOWED->value,
            $e instanceof TooManyRequestsHttpException  => ErrorCode::RATE_LIMITED->value,
            $status === 409                             => ErrorCode::CONFLICT->value,
            $status >= 500                              => ErrorCode::SERVER_ERROR->value,
            $status === 422                             => ErrorCode::UNPROCESSABLE->value,
            $status === 400                             => ErrorCode::BAD_REQUEST->value,
            default                                    => ErrorCode::REQUEST_FAILED->value,
        };
    }
}
