<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Krypton\Table;
use App\Models\Krypton\Session;
use App\Repositories\Krypton\TableRepository;
use App\Repositories\Krypton\OrderRepository;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Enums\OrderStatus;
use App\Services\DashboardService;

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
        $host = (string) (config('reverb.apps.apps.0.options.host') ?? '127.0.0.1');
        $port = (int) (config('reverb.apps.apps.0.options.port') ?? 6001);
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
     * Dashboard for admin
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response
     */
    public function index()
    {   
        // Use the model's built-in error handling (try/catch with graceful fallback)
        $session = Session::getLatestSessionId();

        $reverbStatus = $this->getReverbStatus();

        if( !$session ) {
            return Inertia::render('Dashboard', [
                'title' => 'Dashboard',
                'description' => 'Analytics',
                'tableOrders' => [],
                'openOrders' => [],
                'reverbStatus' => $reverbStatus,
            ]);
        }


        $dashboard = new DashboardService(); 

        $totalSales = $dashboard->totalSales();
        $monthlySales = $dashboard->monthlySales();
        $totalOrders = $dashboard->getTotalOrders();
        $guestCount = $dashboard->getTotalGuests();
        $salesData = $dashboard->getSalesData(7);

        $openOrders = $this->orderRepository->getOpenOrdersForSession($session->id);
        $tableOrders = $this->tableRepository->getActiveTableOrders();

        foreach ($tableOrders as $tableOrder) {
            $device = Device::where('table_id', $tableOrder->table_id)->first();

            if( $device ) {
                $tableOrder->device = $device->load(['table']);
            }
        }

        // Get all devices with order counts
        $devices = Device::with(['table'])
            ->get()
            ->map(function ($device) {
                // Count orders for today
                $todayOrdersCount = DeviceOrder::where('device_id', $device->id)
                    ->whereDate('created_at', today())
                    ->count();

                // Count pending orders
                $pendingOrdersCount = DeviceOrder::where('device_id', $device->id)
                    ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value, OrderStatus::IN_PROGRESS->value])
                    ->count();

                // Get last order time
                $lastOrder = DeviceOrder::where('device_id', $device->id)
                    ->latest('created_at')
                    ->first();

                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'device_id' => $device->device_id,
                    'is_active' => $device->is_active,
                    'table' => $device->table,
                    'today_orders_count' => $todayOrdersCount,
                    'pending_orders_count' => $pendingOrdersCount,
                    'last_order_at' => $lastOrder ? $lastOrder->created_at : null,
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
            'reverbStatus' => $reverbStatus,
            'devices' => $devices,
        ]);
    }
}
