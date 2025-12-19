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

        if( !$session ) {
            return Inertia::render('Dashboard', [
                'title' => 'Dashboard',
                'description' => 'Analytics',
                'tableOrders' => [],
                'openOrders' => []
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
        ]);
    }
}
