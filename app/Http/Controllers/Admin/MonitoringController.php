<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderVoided;
use App\Events\SessionReset;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Krypton\Session as KryptonSession;
use App\Models\PrintEvent;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    /**
     * Display the monitoring dashboard with failure metrics.
     */
    public function index(): Response
    {
        $metrics = $this->getMetrics();

        return Inertia::render('Monitoring/Index', [
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get monitoring metrics (API endpoint for real-time refresh).
     */
    public function metrics(): array
    {
        return $this->getMetrics();
    }

    /**
     * Collect all monitoring metrics.
     */
    private function getMetrics(): array
    {
        // Unprintable orders: device_orders where is_printed = false and created > 10 minutes ago
        $unprintedOrders = DeviceOrder::where('is_printed', false)
            ->where('created_at', '<', now()->subMinutes(10))
            ->whereIn('status', [
                OrderStatus::PENDING->value,
                OrderStatus::CONFIRMED->value,
            ])
            ->with(['device', 'table'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'order_number' => $order->order_number,
                    'device_name' => $order->device?->name,
                    'table_name' => $order->table?->name,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toIso8601String(),
                    'minutes_ago' => $order->created_at->diffInMinutes(now()),
                ];
            });

        // Failed print events: unacknowledged print_events with attempts > 3
        $failedPrintEvents = PrintEvent::where('is_acknowledged', false)
            ->where('attempts', '>', 3)
            ->with(['deviceOrder.device', 'deviceOrder.table'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($evt) {
                return [
                    'id' => $evt->id,
                    'device_order_id' => $evt->device_order_id,
                    'order_id' => $evt->deviceOrder?->order_id,
                    'order_number' => $evt->deviceOrder?->order_number,
                    'device_name' => $evt->deviceOrder?->device?->name,
                    'table_name' => $evt->deviceOrder?->table?->name,
                    'event_type' => $evt->event_type,
                    'attempts' => $evt->attempts,
                    'last_error' => $evt->last_error,
                    'created_at' => $evt->created_at->toIso8601String(),
                ];
            });

        // Queue depth and failed jobs
        $queueDepth = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        // Recent refill errors from logs (past 24 hours)
        // Note: This requires Laravel Log facade; parsing logs is expensive, so we'll count from print_events instead
        $recentRefillErrors = PrintEvent::where('event_type', 'REFILL')
            ->where('is_acknowledged', false)
            ->where('created_at', '>', now()->subDay())
            ->count();

        // Orphaned device_orders: local device_orders with no matching POS order
        // This is expensive; cache for 5 minutes or skip in real-time view
        // Orphaned order detection is not implemented: DeviceOrder.order_id references
        // Krypton\Order which lives on the 'pos' connection; cross-connection subqueries
        // are not supported by Laravel's query builder on a single connection.
        $orphanedOrders = 0;

        // Database connection health
        $mysqlHealthy = $this->checkDatabaseHealth('mysql');
        $posHealthy = $this->checkDatabaseHealth('pos');

        return [
            'unprintedOrders' => [
                'count' => $unprintedOrders->count(),
                'items' => $unprintedOrders,
            ],
            'failedPrintEvents' => [
                'count' => $failedPrintEvents->count(),
                'items' => $failedPrintEvents,
            ],
            'queue' => [
                'pending' => $queueDepth,
                'failed' => $failedJobs,
            ],
            'refillErrors' => $recentRefillErrors,
            'orphanedOrders' => $orphanedOrders,
            'database' => [
                'mysql' => $mysqlHealthy,
                'pos' => $posHealthy,
            ],
            'printLatency' => $this->getPrintLatency(),
            'devices' => $this->getDeviceHealth(),
            'activeSessions' => $this->getActiveSessions(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Print-event latency metrics: p50/p95/max over rolling windows + the
     * most recent N events with the four end-to-end timestamps.
     *
     * Stats are computed in PHP (not SQL) to stay portable across MySQL +
     * SQLite (test env). Volume is bounded by the 7-day window and only the
     * `created_at` + `acknowledged_at` columns are loaded.
     *
     * The "stuck" count surfaces events whose broadcast went out but were
     * never acked — these are the operationally important ones to watch.
     */
    private function getPrintLatency(): array
    {
        $windows = [
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
        ];

        $stats = [];
        foreach ($windows as $label => $since) {
            $rows = DB::table('print_events')
                ->select(['created_at', 'acknowledged_at'])
                ->where('created_at', '>=', $since)
                ->get();

            $total = $rows->count();
            $deltas = [];
            foreach ($rows as $r) {
                if ($r->acknowledged_at === null || $r->created_at === null) {
                    continue;
                }
                $diff = Carbon::parse($r->acknowledged_at)->diffInSeconds(Carbon::parse($r->created_at));
                if ($diff >= 0) {
                    $deltas[] = $diff;
                }
            }

            sort($deltas);
            $acked = count($deltas);
            $avg = $acked > 0 ? array_sum($deltas) / $acked : null;
            $max = $acked > 0 ? end($deltas) : null;
            $p50 = $acked > 0 ? $deltas[(int) floor($acked * 0.50)] ?? null : null;
            $p95 = $acked > 0 ? $deltas[(int) floor($acked * 0.95)] ?? null : null;

            $stats[$label] = [
                'total' => $total,
                'acked' => $acked,
                'avg_sec' => $avg !== null ? (float) $avg : null,
                'p50_sec' => $p50 !== null ? (int) $p50 : null,
                'p95_sec' => $p95 !== null ? (int) $p95 : null,
                'max_sec' => $max !== null ? (int) $max : null,
            ];
        }

        // Stuck = broadcast went out but never acked, older than 2 min.
        $stuck = PrintEvent::whereNotNull('broadcast_at')
            ->whereNull('acknowledged_at')
            ->where('broadcast_at', '<', now()->subMinutes(2))
            ->count();

        // Recent 30 events with full timeline. `withCount` avoids N+1 on pei_count
        // (would otherwise issue 30 extra COUNT queries per poll, every 30s).
        $recent = PrintEvent::with(['deviceOrder.table', 'deviceOrder.device'])
            ->withCount('printEventItems')
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function ($e) {
                $created = $e->created_at;
                $acked = $e->acknowledged_at;
                $totalSec = ($created && $acked) ? $created->diffInSeconds($acked) : null;

                return [
                    'id' => $e->id,
                    'device_order_id' => $e->device_order_id,
                    'order_number' => $e->deviceOrder?->order_number,
                    'table_name' => $e->deviceOrder?->table?->name,
                    'device_name' => $e->deviceOrder?->device?->name,
                    'event_type' => $e->event_type,
                    'pei_count' => (int) ($e->print_event_items_count ?? 0),
                    'created_at' => $created?->toIso8601String(),
                    'broadcast_at' => $e->broadcast_at?->toIso8601String(),
                    'reserved_at' => $e->reserved_at?->toIso8601String(),
                    'acknowledged_at' => $acked?->toIso8601String(),
                    'total_sec' => $totalSec,
                    'is_acknowledged' => (bool) $e->is_acknowledged,
                ];
            });

        return [
            'windows' => $stats,
            'stuck' => $stuck,
            'recent' => $recent,
        ];
    }

    /**
     * Per-device health: registered devices with last_seen_at + traffic-light state.
     */
    private function getDeviceHealth(): array
    {
        $rows = Device::query()
            ->orderBy('id')
            ->get(['id', 'name', 'table_id', 'is_active', 'last_seen_at', 'branch_id'])
            ->map(function ($d) {
                $lastSeen = $d->last_seen_at;
                // abs() guards against clock skew — if last_seen_at is in the
                // future (device clock ahead of server), diffInSeconds can be
                // negative and the green/yellow/red threshold check breaks.
                $secsSince = $lastSeen ? abs(now()->diffInSeconds($lastSeen)) : null;
                $state = 'unknown';
                if ($lastSeen) {
                    if ($secsSince <= 60) {
                        $state = 'green';
                    } elseif ($secsSince <= 180) {
                        $state = 'yellow';
                    } else {
                        $state = 'red';
                    }
                }

                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'table_id' => $d->table_id,
                    'is_active' => (bool) $d->is_active,
                    'last_seen_at' => $lastSeen?->toIso8601String(),
                    'last_seen_secs_ago' => $secsSince,
                    'state' => $state,
                ];
            });

        return $rows->values()->all();
    }

    /**
     * Active KryptonSessions with their open device_orders + payment state.
     * Used by the admin action panel for Reset / Force-end decisions.
     *
     * Cached for 20s because every call hits the POS DB twice (latest session
     * + per-order payment state). At a 30s admin-page poll cadence the cache
     * still feels live, and one slow POS query no longer stalls the whole
     * /monitoring/metrics response. Cache is busted from reset/force-end so
     * post-action refresh always reflects the new state.
     */
    private function getActiveSessions(): array
    {
        return Cache::remember('monitoring:active_sessions', 20, fn () => $this->computeActiveSessions());
    }

    private function computeActiveSessions(): array
    {
        try {
            $latest = KryptonSession::getLatestSession();
        } catch (\Throwable $e) {
            Log::warning('Monitoring: getLatestSession failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $latest || $latest->date_time_closed !== null) {
            return [];
        }

        $sessionId = (int) $latest->id;

        $openStatuses = [
            OrderStatus::PENDING->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::IN_PROGRESS->value,
            OrderStatus::READY->value,
            OrderStatus::SERVED->value,
        ];

        $deviceOrders = DeviceOrder::query()
            ->where('session_id', $sessionId)
            ->with(['table', 'device'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Look up POS payment state per order to surface "paid? yes/no/unknown".
        $posOrderIds = $deviceOrders
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->values();

        $posOrders = collect();
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
            }
        }

        $unpaidCount = 0;

        $orderRows = $deviceOrders->map(function ($o) use ($posOrders, $posReachable, $openStatuses, &$unpaidCount) {
            $status = is_object($o->status) ? $o->status->value : $o->status;
            $isOpen = in_array($status, $openStatuses, true);

            $pos = $o->order_id ? $posOrders->get((int) $o->order_id) : null;
            $paymentState = 'unknown';
            if (! $posReachable) {
                $paymentState = 'pos_unreachable';
            } elseif (! $o->order_id) {
                $paymentState = 'no_pos_link';
            } elseif (! $pos) {
                $paymentState = 'pos_missing';
            } elseif ((int) ($pos->is_voided ?? 0) === 1) {
                $paymentState = 'voided';
            } elseif ((int) ($pos->is_open ?? 1) === 0 || $pos->date_time_closed !== null) {
                $paymentState = 'paid';
            } else {
                $paymentState = 'unpaid';
            }

            if ($isOpen && $paymentState === 'unpaid') {
                $unpaidCount++;
            }

            return [
                'id' => $o->id,
                'order_id' => $o->order_id,
                'order_number' => $o->order_number,
                'status' => $status,
                'is_open' => $isOpen,
                'table_name' => $o->table?->name,
                'device_name' => $o->device?->name,
                'guest_count' => $o->guest_count,
                'total' => $o->total,
                'payment_state' => $paymentState,
                'created_at' => $o->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return [
            [
                'session_id' => $sessionId,
                'opened_at' => $latest->date_time_opened,
                'pos_reachable' => $posReachable,
                'unpaid_count' => $unpaidCount,
                'orders' => $orderRows,
                'can_safely_force_end' => $posReachable && $unpaidCount === 0,
            ],
        ];
    }

    /**
     * Reset a session: bumps server-side cache version and broadcasts
     * SessionReset so connected tablets clear local state. Lightweight —
     * the session itself remains open in POS.
     *
     * Admin-only (route already protected by can:admin).
     */
    public function resetSession(int $id): JsonResponse
    {
        $versionKey = "session:{$id}:version";
        if (Cache::has($versionKey)) {
            $version = Cache::increment($versionKey);
        } else {
            Cache::put($versionKey, 1);
            $version = 1;
        }

        // Bust the cached active-sessions snapshot so the next /metrics poll
        // shows the post-action state immediately instead of waiting 20s.
        Cache::forget('monitoring:active_sessions');

        $broadcastError = null;
        try {
            SessionReset::dispatch($id, $version);
        } catch (\Throwable $e) {
            $broadcastError = $e->getMessage();
            Log::warning('Monitoring::resetSession broadcast failed', [
                'session_id' => $id,
                'error' => $broadcastError,
            ]);
        }

        Log::info('Monitoring: session reset dispatched', [
            'session_id' => $id,
            'version' => $version,
            'broadcast_error' => $broadcastError,
        ]);

        return response()->json([
            'success' => $broadcastError === null,
            'session_id' => $id,
            'version' => $version,
            'broadcast_error' => $broadcastError,
            'message' => $broadcastError === null
                ? "Session {$id} reset broadcast dispatched."
                : "Session {$id} cache updated, but broadcast failed: {$broadcastError}",
        ]);
    }

    /**
     * Force-end a session: voids any open device_orders + broadcasts
     * SessionReset. Guard rails:
     *   - Refuses by default if any order is still open & unpaid in POS.
     *   - `force=true` overrides the guard (audit-logged).
     *   - POS-unreachable is treated as "cannot verify" — refuses unless force.
     *
     * Admin-only. Mirrors SessionApiController::forceEnd but exposed via
     * web (session) auth instead of Sanctum.
     */
    public function forceEndSession(int $id, Request $request): JsonResponse
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
            ->where('session_id', $id)
            ->whereIn('status', $openStatuses)
            ->get();

        if ($openOrders->isEmpty()) {
            $this->dispatchSessionReset($id);

            return response()->json([
                'success' => true,
                'closed' => 0,
                'message' => "No open orders for session {$id}. Reset broadcast dispatched.",
            ]);
        }

        $posOrderIds = $openOrders
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->filter(fn ($oid) => is_numeric($oid) && (int) $oid > 0)
            ->map(fn ($oid) => (int) $oid)
            ->values();

        $posOrders = collect();
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
                if (! $force) {
                    return response()->json([
                        'success' => false,
                        'message' => 'POS DB unreachable — cannot confirm payment state. Re-submit with force=true to override.',
                    ], 422);
                }
            }
        }

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
                    'success' => false,
                    'message' => 'Cannot force-end — orders still open & unpaid in POS. Cashier must close them in POS first, or re-submit with force=true.',
                    'blocking_ids' => $blockers->pluck('id')->values(),
                    'pos_order_ids' => $blockers->pluck('order_id')->values(),
                ], 422);
            }
        }

        $closed = 0;
        foreach ($openOrders as $order) {
            $next = $this->resolveTerminalStatus($order, $posOrders, $posReachable);
            $prev = is_object($order->status) ? $order->status->value : $order->status;

            $updated = DB::table('device_orders')
                ->where('id', (int) $order->id)
                ->update(['status' => $next->value, 'updated_at' => now()]);

            if ($updated === 0) {
                continue;
            }

            $closed++;
            AuditLogService::orderStatusChanged($request, (int) $order->id, $prev, $next->value, null, 'admin:force-end-session');

            $fresh = DeviceOrder::find((int) $order->id);
            if ($fresh) {
                OrderStatusUpdated::dispatch($fresh);
                $next === OrderStatus::COMPLETED
                    ? OrderCompleted::dispatch($fresh)
                    : OrderVoided::dispatch($fresh);
            }
        }

        $this->dispatchSessionReset($id);

        // Bust the cached active-sessions snapshot so the next /metrics poll
        // reflects the new state immediately rather than waiting up to 20s.
        Cache::forget('monitoring:active_sessions');

        Log::warning('Monitoring: session force-ended', [
            'session_id' => $id,
            'closed' => $closed,
            'force' => $force,
            'pos_reachable' => $posReachable,
            'admin_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'closed' => $closed,
            'force' => $force,
            'message' => "Session {$id} force-ended. {$closed} order(s) closed. Reset broadcast dispatched.",
        ]);
    }

    private function resolveTerminalStatus(DeviceOrder $order, $posOrders, bool $posReachable): OrderStatus
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

    private function dispatchSessionReset(int $sessionId): void
    {
        try {
            $version = Cache::increment("session:{$sessionId}:version");
            SessionReset::dispatch($sessionId, $version);
        } catch (\Throwable $e) {
            Log::warning('Monitoring::dispatchSessionReset failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check database connection health.
     */
    private function checkDatabaseHealth(string $connection): bool
    {
        try {
            DB::connection($connection)->getPdo();
            DB::connection($connection)->select('SELECT 1');

            return true;
        } catch (\Throwable $e) {
            Log::error("Database connection failed: {$connection}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Purge acknowledged print_events older than 24 hours.
     * These are transient handshakes; device_order.is_printed is the source of truth.
     */
    public function purgePrintEvents(): array
    {
        $deleted = PrintEvent::where('is_acknowledged', true)
            ->where('acknowledged_at', '<', now()->subDay())
            ->delete();

        Log::info('Purged acknowledged print_events', ['count' => $deleted]);

        return [
            'success' => true,
            'deleted' => $deleted,
            'message' => "Purged {$deleted} acknowledged print events older than 24 hours",
        ];
    }
}
