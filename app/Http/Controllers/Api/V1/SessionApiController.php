<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderVoided;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Krypton\Session as KryptonSession;
use App\Events\SessionReset;

class SessionApiController extends Controller
{
    /**
     * Return current active session for a branch.
     * 
     * Response shape: the session object at root level (or null when none active).
     * Clients access `response.data.id` via Axios — do NOT nest under a `session` key.
     */
    public function current(Request $request)
    {
        try {
            $session = KryptonSession::getLatestSession();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch latest session: ' . $e->getMessage());
            $session = null;
        }

        // Safety: return null if session is closed (date_time_closed is set).
        // NOTE: KryptonSession is the shared POS cashier session — all tablets in the
        // same restaurant use the same session ID by design. Device-scoped filtering
        // does not apply here; device isolation is enforced at the order level via device_id.
        if ($session && isset($session->date_time_closed) && $session->date_time_closed !== null) {
            Log::info('SessionApiController@current: Latest session is closed, returning null', ['session_id' => $session->id]);
            $session = null;
        }

        // Return session nested under `data` with server-authoritative timing so PWA
        // clients can correct for clock skew. The existing `responseData?.data ?? responseData`
        // fallback in the PWA store handles both this new format and any cached old responses.
        return response()->json([
            'data'                    => $session,
            'server_time'             => now()->toIso8601String(),
            'session_started_at'      => $session?->date_time_opened,
            'session_duration_seconds'=> $session ? 14400 : null,
        ]);
    }

    /**
     * Return the latest active session wrapped under a `session` key.
     *
     * Called by the print-bridge (GET /api/devices/latest-session).
     * Distinct from current() which returns the session at root level for Axios clients.
     *
     * Response shape: { session: { id, ... } } or { session: null }
     *
     * @unauthenticated
     */
    public function latestSession(Request $request)
    {
        try {
            $session = KryptonSession::getLatestSession();
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch latest session (latestSession): ' . $e->getMessage());
            $session = null;
        }

        return response()->json(['session' => $session]);
    }

    /**
     * Return session metadata.
     */
    public function show(Request $request, int $id)
    {
        $s = KryptonSession::find($id);
        if (! $s) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        $isActive = (isset($s->status) && strtoupper($s->status) === 'ACTIVE') || (isset($s->date_time_closed) && $s->date_time_closed === null);

        return response()->json(['success' => true, 'session' => $s, 'is_active' => (bool)$isActive]);
    }

    /**
     * Reset a session: clear server caches and broadcast a session.reset event so clients can clear local caches.
     * Requires `auth:sanctum` and administrative access or device.
     */
    public function reset(Request $request, int $id)
    {
        $user = $request->user();
        // Allow admins (User->is_admin) or devices
        $isAdmin = isset($user->is_admin) && $user->is_admin;
        $isDevice = $user instanceof Device;

        if (! $isAdmin && ! $isDevice) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // bump session version in cache
        $versionKey = "session:{$id}:version";
        if (! Cache::has($versionKey)) {
            Cache::put($versionKey, 1);
            $version = 1;
        } else {
            $version = Cache::increment($versionKey);
        }

        // broadcast reset event
        try {
            SessionReset::dispatch($id, $version);
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch SessionReset: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Session reset dispatched', 'version' => $version]);
    }

    /**
     * Force-end a stuck session: close any open device orders then broadcast session.reset.
     *
     * Body: { "force": bool }
     *   force=false (default) — refuses if any associated order is still open in the POS.
     *   force=true            — voids all open orders locally regardless of POS state.
     *
     * Requires auth:sanctum (admin token).
     */
    public function forceEnd(Request $request, int $sessionId): JsonResponse
    {
        $force = (bool) $request->input('force', false);

        $openStatuses = [
            OrderStatus::PENDING->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::IN_PROGRESS->value,
            OrderStatus::READY->value,
            OrderStatus::SERVED->value,
        ];

        $openOrders = DeviceOrder::query()
            ->where('session_id', $sessionId)
            ->whereIn('status', $openStatuses)
            ->get();

        if ($openOrders->isEmpty()) {
            $this->doSessionReset($sessionId);
            return response()->json([
                'success' => true,
                'message' => 'No open orders — session reset dispatched.',
                'closed'  => 0,
            ]);
        }

        // Query POS for linked order IDs.
        $posOrderIds = $openOrders
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->values();

        $posOrders   = collect();
        $posReachable = true;

        if ($posOrderIds->isNotEmpty()) {
            try {
                $posOrders = DB::connection('pos')
                    ->table('orders')
                    ->select(['id', 'is_voided', 'is_open', 'date_time_closed'])
                    ->whereIn('id', $posOrderIds)
                    ->get()
                    ->mapWithKeys(fn ($o) => [(int) $o->id => $o]);
            } catch (\Throwable $e) {
                $posReachable = false;
                Log::warning('[SessionApiController::forceEnd] POS DB unreachable', ['error' => $e->getMessage()]);
                if (! $force) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot confirm POS order state — POS DB unreachable. Pass force=true to void locally.',
                    ], 422);
                }
            }
        }

        // Without force, block if any order is genuinely still open in POS.
        if ($posReachable && ! $force) {
            $blockers = $openOrders->filter(function ($order) use ($posOrders) {
                if (! $order->order_id) {
                    return false;
                }
                $pos = $posOrders->get((int) $order->order_id);
                if (! $pos) {
                    return false;
                }
                return (int) ($pos->is_voided ?? 0) === 0
                    && (int) ($pos->is_open ?? 1) === 1
                    && $pos->date_time_closed === null;
            });

            if ($blockers->isNotEmpty()) {
                return response()->json([
                    'success'      => false,
                    'message'      => 'One or more orders are still open in the POS. Close them in the POS first, or send force=true.',
                    'blocking_ids' => $blockers->pluck('id')->values(),
                    'pos_order_ids'=> $blockers->pluck('order_id')->values(),
                ], 422);
            }
        }

        $closed = 0;
        foreach ($openOrders as $order) {
            $nextStatus = $this->resolveNextOrderStatus($order, $posOrders, $posReachable);
            $prevStatus = is_string($order->status) ? $order->status : $order->status->value;

            $updated = DB::table('device_orders')
                ->where('id', (int) $order->id)
                ->update(['status' => $nextStatus->value, 'updated_at' => now()]);

            if ($updated === 0) {
                continue;
            }

            $closed++;

            AuditLogService::orderStatusChanged(
                $request,
                (int) $order->id,
                $prevStatus,
                $nextStatus->value,
                null,
                'admin:force-end-session'
            );

            $fresh = DeviceOrder::query()->find((int) $order->id);
            if ($fresh) {
                OrderStatusUpdated::dispatch($fresh);
                $nextStatus === OrderStatus::COMPLETED ? OrderCompleted::dispatch($fresh) : OrderVoided::dispatch($fresh);
            }
        }

        $this->doSessionReset($sessionId);

        return response()->json([
            'success' => true,
            'message' => "Session {$sessionId} force-ended. Orders closed: {$closed}.",
            'closed'  => $closed,
        ]);
    }

    private function resolveNextOrderStatus(DeviceOrder $order, $posOrders, bool $posReachable): OrderStatus
    {
        if (! $order->order_id || ! $posReachable) {
            return OrderStatus::VOIDED;
        }

        $pos = $posOrders->get((int) $order->order_id);
        if (! $pos) {
            return OrderStatus::VOIDED;
        }
        if ((int) ($pos->is_voided ?? 0) === 1) {
            return OrderStatus::VOIDED;
        }
        if ((int) ($pos->is_open ?? 1) === 0 || $pos->date_time_closed !== null) {
            return OrderStatus::COMPLETED;
        }
        return OrderStatus::VOIDED;
    }

    private function doSessionReset(int $sessionId): void
    {
        try {
            $version = Cache::increment("session:{$sessionId}:version");
            SessionReset::dispatch($sessionId, $version);
        } catch (\Throwable $e) {
            Log::warning('[SessionApiController::forceEnd] SessionReset broadcast failed', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
