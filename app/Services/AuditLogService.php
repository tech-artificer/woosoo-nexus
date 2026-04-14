<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Task 3.1 (Mission-8): Central audit log writer.
 *
 * Usage:
 *   AuditLogService::log('order.status_changed', $request, [
 *       'actor'   => ['type' => 'device', 'id' => $device->id],
 *       'subject' => ['type' => 'DeviceOrder', 'id' => $order->id],
 *       'meta'    => ['old_status' => 'PENDING', 'new_status' => 'CONFIRMED'],
 *   ]);
 *
 * All audit events are fire-and-forget with a try/catch — a logging failure
 * must NEVER propagate to the caller or abort a business transaction.
 */
class AuditLogService
{
    /**
     * Write one audit record.
     *
     * @param  string        $event    Dot-namespaced event key (e.g. 'order.status_changed')
     * @param  Request|null  $request  Current HTTP request (for IP + request_id)
     * @param  array{
     *   actor?: array{type: string, id: int|null},
     *   subject?: array{type: string, id: int|null},
     *   meta?: array<string, mixed>,
     * } $context
     */
    public static function log(string $event, ?Request $request = null, array $context = []): void
    {
        try {
            AuditLog::create([
                'event'        => $event,
                'actor_type'   => $context['actor']['type']  ?? null,
                'actor_id'     => $context['actor']['id']    ?? null,
                'subject_type' => $context['subject']['type'] ?? null,
                'subject_id'   => $context['subject']['id']  ?? null,
                'meta'         => $context['meta']           ?? null,
                'ip_address'   => $request?->ip(),
                'request_id'   => $request?->attributes->get('request_id'),
            ]);
        } catch (\Throwable $e) {
            // Never abort the caller — log to Laravel's own logger as a fallback.
            Log::error('[AuditLogService] Failed to write audit record', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ----------------------------------------------------------------
    // Convenience helpers — named methods for the most common events
    // ----------------------------------------------------------------

    public static function orderStatusChanged(
        Request $request,
        int $orderId,
        string $oldStatus,
        string $newStatus,
        ?int $actorId = null,
        string $actorType = 'device'
    ): void {
        static::log('order.status_changed', $request, [
            'actor'   => ['type' => $actorType, 'id' => $actorId],
            'subject' => ['type' => 'DeviceOrder', 'id' => $orderId],
            'meta'    => ['old_status' => $oldStatus, 'new_status' => $newStatus],
        ]);
    }

    public static function deviceRegistered(Request $request, int $deviceId): void
    {
        static::log('device.registered', $request, [
            'actor'   => ['type' => 'system', 'id' => null],
            'subject' => ['type' => 'Device', 'id' => $deviceId],
        ]);
    }

    public static function sessionStarted(Request $request, int $sessionId, int $deviceId): void
    {
        static::log('session.started', $request, [
            'actor'   => ['type' => 'device', 'id' => $deviceId],
            'subject' => ['type' => 'KryptonSession', 'id' => $sessionId],
        ]);
    }

    public static function sessionEnded(Request $request, int $sessionId, int $deviceId): void
    {
        static::log('session.ended', $request, [
            'actor'   => ['type' => 'device', 'id' => $deviceId],
            'subject' => ['type' => 'KryptonSession', 'id' => $sessionId],
        ]);
    }

    public static function authFailed(Request $request, string $reason): void
    {
        static::log('auth.failed', $request, [
            'meta' => ['reason' => $reason],
        ]);
    }

    public static function adminAction(Request $request, string $action, int $actorId, array $meta = []): void
    {
        static::log('admin.' . $action, $request, [
            'actor' => ['type' => 'user', 'id' => $actorId],
            'meta'  => $meta,
        ]);
    }
}
