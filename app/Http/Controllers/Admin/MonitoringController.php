<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
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
            ->whereIn('status', ['PENDING', 'CONFIRMED'])
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
        $orphanedOrders = cache()->remember('monitoring.orphaned_orders', 300, function () {
            try {
                // Check for device_orders where order_id doesn't exist in krypton_woosoo.orders
                // Note: This requires a LEFT JOIN across connections, which Laravel doesn't support natively
                // Alternative: Count device_orders with null order relationship (if eager-loaded)
                return DeviceOrder::whereNotNull('order_id')
                    ->whereDoesntHave('order') // This only works if Order relationship is defined
                    ->count();
            } catch (\Throwable $e) {
                Log::warning('Failed to count orphaned orders', ['error' => $e->getMessage()]);
                return 0;
            }
        });

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
            'timestamp' => now()->toIso8601String(),
        ];
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
