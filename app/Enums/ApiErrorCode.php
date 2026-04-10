<?php

namespace App\Enums;

enum ApiErrorCode: string
{
    // ── HTTP / generic ──────────────────────────────────────────────────────
    case VALIDATION_ERROR     = 'VALIDATION_ERROR';
    case UNAUTHENTICATED      = 'UNAUTHENTICATED';
    case FORBIDDEN            = 'FORBIDDEN';
    case NOT_FOUND            = 'NOT_FOUND';
    case METHOD_NOT_ALLOWED   = 'METHOD_NOT_ALLOWED';
    case RATE_LIMITED         = 'RATE_LIMITED';
    case CONFLICT             = 'CONFLICT';
    case SERVER_ERROR         = 'SERVER_ERROR';
    case UNPROCESSABLE        = 'UNPROCESSABLE';
    case BAD_REQUEST          = 'BAD_REQUEST';
    case REQUEST_FAILED       = 'REQUEST_FAILED';

    // ── Domain-specific ─────────────────────────────────────────────────────
    case ORDER_ALREADY_EXISTS  = 'ORDER_ALREADY_EXISTS';   // 409 — duplicate submission (idempotency)
    case SESSION_EXPIRED       = 'SESSION_EXPIRED';        // 403 — session ended before order was submitted
    case DEVICE_NOT_ASSIGNED   = 'DEVICE_NOT_ASSIGNED';    // 422 — device has no table assigned
    case SESSION_NOT_FOUND     = 'SESSION_NOT_FOUND';      // 404 — session record missing
    case PRINT_EVENT_NOT_FOUND = 'PRINT_EVENT_NOT_FOUND';  // 404 — print event record missing
    case DEVICE_INACTIVE       = 'DEVICE_INACTIVE';        // 403 — device account is suspended

    /**
     * Human-readable description for logging / support.
     */
    public function label(): string
    {
        return match ($this) {
            self::VALIDATION_ERROR     => 'Validation failed',
            self::UNAUTHENTICATED      => 'Authentication required',
            self::FORBIDDEN            => 'Insufficient permissions',
            self::NOT_FOUND            => 'Resource not found',
            self::METHOD_NOT_ALLOWED   => 'HTTP method not allowed',
            self::RATE_LIMITED         => 'Too many requests',
            self::CONFLICT             => 'Resource conflict',
            self::SERVER_ERROR         => 'Internal server error',
            self::UNPROCESSABLE        => 'Unprocessable entity',
            self::BAD_REQUEST          => 'Bad request',
            self::REQUEST_FAILED       => 'Request failed',
            self::ORDER_ALREADY_EXISTS => 'Order already exists for this idempotency key',
            self::SESSION_EXPIRED      => 'Session has already been closed',
            self::DEVICE_NOT_ASSIGNED  => 'Device is not assigned to a table',
            self::SESSION_NOT_FOUND    => 'No active session found for this device',
            self::PRINT_EVENT_NOT_FOUND => 'Print event record not found',
            self::DEVICE_INACTIVE      => 'Device account is inactive',
        };
    }
}
