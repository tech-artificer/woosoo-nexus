<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Krypton\Session;
use App\Repositories\Krypton\OrderRepository;
use App\Repositories\Krypton\TableRepository;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $tableRepository;

    protected $orderRepository;

    public function __construct(TableRepository $tableRepository, OrderRepository $orderRepository)
    {
        $this->tableRepository = $tableRepository;
        $this->orderRepository = $orderRepository;
    }

    protected function getReverbStatus(): array
    {
        $host = '127.0.0.1';
        $port = (int) config('reverb.servers.reverb.port', 8080);
        $timeout = 0.3;
        $start = microtime(true);
        $errno = 0;
        $errstr = '';

        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        if ($fp) {
            fclose($fp);

            return [
                'ok' => true,
                'host' => $host,
                'port' => $port,
                'latencyMs' => $latencyMs,
                'checkedAt' => now()->toIso8601String(),
            ];
        }

        return [
            'ok' => false,
            'host' => $host,
            'port' => $port,
            'error' => $errstr ?: 'Connection failed',
            'latencyMs' => $latencyMs,
            'checkedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * Dashboard for admin.
     */
    public function index()
    {
        // Use the model's built-in error handling (try/catch with graceful fallback)
        $session = Session::getLatestSessionId();
        if (! $session && app()->runningUnitTests()) {
            $testSessionId = Cache::get('testing.krypton.session_id');

            if (is_numeric($testSessionId)) {
                $session = (object) ['id' => (int) $testSessionId];
            }
        }

        $reverbStatus = $this->getReverbStatus();

        if (! $session) {
            session()->flash('warning', 'Dashboard data is unavailable — POS system is currently offline.');

            return Inertia::render('Dashboard', [
                'title' => 'Dashboard',
                'description' => 'Analytics',
                'tableOrders' => [],
                'openOrders' => [],
                'reverbStatus' => $reverbStatus,
                'sessionId' => null,
                'totalSales' => '0.00',
                'totalOrders' => 0,
                'guestCount' => 0,
                'monthlySales' => '0.00',
                'salesData' => [],
                'topItems' => [],
                'devices' => [],
            ]);
        }

        $dashboard = new DashboardService;

        $totalSales = $dashboard->totalSales();
        $monthlySales = $dashboard->monthlySales();
        $totalOrders = $dashboard->getTotalOrders();
        $guestCount = $dashboard->getTotalGuests();
        $salesData = $dashboard->getSalesData(7);
        $topItems = $dashboard->getTopItems(6);

        $openOrders = $this->orderRepository->getOpenOrdersForSession($session->id);
        $tableOrders = $this->tableRepository->getActiveTableOrders();
        $tableIds = $tableOrders->pluck('table_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (empty($tableIds)) {
            $tableIds = $tableOrders->pluck('id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        $devicesByTableId = Device::with('table')
            ->when(! empty($tableIds), fn ($query) => $query->whereIn('table_id', $tableIds))
            ->get()
            ->keyBy('table_id');

        foreach ($tableOrders as $tableOrder) {
            $tableId = $tableOrder->table_id ?? $tableOrder->id;
            $device = $devicesByTableId->get($tableId);
            if ($device) {
                $tableOrder->device = $device;
            }
        }

        // Get all devices with order counts
        $devices = Device::with(['table'])->get();
        $deviceStats = collect();

        if ($devices->isNotEmpty()) {
            $deviceIds = $devices->pluck('id')->all();
            $deviceStats = DeviceOrder::query()
                ->whereIn('device_id', $deviceIds)
                ->selectRaw(
                    'device_id, COUNT(*) as total_orders_count, SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today_orders_count, SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as pending_orders_count, MAX(created_at) as last_order_at',
                    [
                        today()->toDateString(),
                        OrderStatus::PENDING->value,
                        OrderStatus::CONFIRMED->value,
                        OrderStatus::IN_PROGRESS->value,
                    ]
                )
                ->groupBy('device_id')
                ->get()
                ->keyBy('device_id');
        }

        $devices = $devices->map(function ($device) use ($deviceStats) {
            $stats = $deviceStats->get($device->id);

            return [
                'id' => $device->id,
                'name' => $device->name,
                'device_id' => $device->device_id,
                'is_active' => $device->is_active,
                'table' => $device->table,
                'today_orders_count' => (int) ($stats->today_orders_count ?? 0),
                'pending_orders_count' => (int) ($stats->pending_orders_count ?? 0),
                'last_order_at' => $stats->last_order_at ?? null,
                'bluetooth_address' => $device->bluetooth_address,
                'printer_name' => $device->printer_name,
            ];
        });

        return Inertia::render('Dashboard', [
            'title' => 'Dashboard',
            'description' => 'Analytics',
            'tableOrders' => $tableOrders,
            'openOrders' => $openOrders,
            'sessionId' => $session->id,
            'totalSales' => $totalSales,
            'totalOrders' => $totalOrders,
            'guestCount' => $guestCount,
            'monthlySales' => $monthlySales,
            'salesData' => $salesData,
            'topItems' => $topItems,
            'reverbStatus' => $reverbStatus,
            'devices' => $devices,
        ]);
    }

    /**
     * GET /dashboard/stats
     * Returns lightweight real-time counts for dashboard widgets.
     * Authenticated (session-based) only.
     */
    public function apiStats(Request $request): JsonResponse
    {
        // Normalize unknown ranges to 'today' so the echoed `range` matches the
        // window actually used for the queries below (no client/server desync).
        $range = in_array($request->input('range'), ['today', 'week', 'month'], true)
            ? $request->input('range')
            : 'today';
        $dashboard = new DashboardService;
        $reverbStatus = $this->getReverbStatus();

        $startDate = match ($range) {
            'week' => now()->subDays(6)->startOfDay(),
            'month' => now()->subDays(29)->startOfDay(),
            default => today()->startOfDay(),
        };
        $endDate = now()->endOfDay();

        $pendingStatuses = [
            OrderStatus::PENDING->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::IN_PROGRESS->value,
        ];

        $activeDevices = Device::where('is_active', true)->count();
        $pendingOrders = DeviceOrder::whereIn('status', $pendingStatuses)->count();
        $rangeRevenue = DeviceOrder::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotIn('status', [OrderStatus::VOIDED->value, OrderStatus::CANCELLED->value])
            ->sum('total');
        $rangeOrderCount = DeviceOrder::whereBetween('created_at', [$startDate, $endDate])->count();

        return response()->json([
            'range' => $range,
            'active_devices' => $activeDevices,
            'pending_orders' => $pendingOrders,
            'today_revenue' => (float) $rangeRevenue,
            'today_orders' => $rangeOrderCount,
            'total_sales' => $dashboard->totalSales($startDate, $endDate),
            'total_orders' => $dashboard->getTotalOrders($startDate, $endDate),
            'guest_count' => $dashboard->getTotalGuests($startDate, $endDate),
            'sales_data' => $dashboard->getSalesData($range === 'month' ? 30 : ($range === 'week' ? 7 : 1)),
            'reverb' => $reverbStatus,
            'top_items' => $dashboard->getTopItems(5, $startDate, $endDate),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
