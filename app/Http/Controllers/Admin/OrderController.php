<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Krypton\KryptonContextService;

use Inertia\Inertia;
use App\Repositories\Krypton\OrderRepository;
use App\Repositories\Krypton\TableRepository;
use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
use App\Models\DeviceOrder;
use App\Enums\OrderStatus;
use App\Models\Krypton\Session;
use App\Services\Krypton\OrderService;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    
    protected $orderService;

    public function __construct(OrderService $orderService) {
        $this->orderService = $orderService;
    }
    /**
     * Render the orders page.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $tableRepo = new TableRepository();
        $activeOrders = $tableRepo->getActiveTableOrders();

        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            $session = Session::orderByDesc('id')->first();
        } else {
            $session = Session::fromQuery('CALL get_latest_session_id()')->first();
        }

        $orders = DeviceOrder::with(['device', 'order', 'table', 'serviceRequests'])
                ->where('session_id', $session->id)
                ->activeOrder()
                ->orderBy('table_id', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

        $orderHistory = DeviceOrder::with(['device', 'order', 'table', 'serviceRequests'])
                ->where('session_id', $session->id)
                ->completedOrder()
                ->orderBy('updated_at', 'desc')
                ->get();
        
        // simple stats and sparkline for orders
        $today = \Carbon\Carbon::today();
        $start = $today->copy()->subDays(6)->startOfDay();

        $daily = DeviceOrder::where('created_at', '>=', $start)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as cnt")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('cnt', 'date')
            ->toArray();

        $spark = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $spark[] = isset($daily[$d]) ? (int) $daily[$d] : 0;
        }

        $stats = [
            [ 'title' => 'Live Orders', 'value' => $orders->count(), 'subtitle' => 'Pending & in-progress', 'variant' => 'primary', 'sparkline' => $spark ],
            [ 'title' => 'Order History', 'value' => $orderHistory->count(), 'subtitle' => 'Completed / Voided', 'variant' => 'default' ],
        ];

        return Inertia::render('Orders/Index', [
            'title' => 'Orders',
            'description' => 'Daily Orders',    
            'orders' => $orders,
            'orderHistory' => $orderHistory,
            'stats' => $stats,
            // 'user' => auth()->user(),
            // 'tableOrders' => $activeOrders,
        ]);
    }
    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        return back()->with(['success' => true]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {   
        $deviceOrder = DeviceOrder::find($id); 
      
        $deviceOrder->update(['status' => OrderStatus::VOIDED]);
        $this->orderService->voidOrder($deviceOrder);
         //Run the console command
        // $exitCode = Artisan::call('broadcast:order-voided', [
        //     'order_id' => $deviceOrder->order_id
        // ]);

        // Optional: capture output
        // $output = Artisan::output();

        return redirect()->back()->with('success');
    }

    public function complete(Request $request) {

        $orderId = $request->input('order_id');

        //Run the console command
        $exitCode = Artisan::call('broadcast:order-completed', [
            'order_id' => $orderId
        ]);

        // Optional: capture output
        $output = Artisan::output();

     
        return redirect()->back()->with('success');
    }

    /**
     * Bulk complete orders
     */
    public function bulkComplete(Request $request) {
        $validated = $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['required', 'integer'],
        ]);

        $completed = 0;
        foreach ($validated['order_ids'] as $orderId) {
            try {
                Artisan::call('broadcast:order-completed', [
                    'order_id' => $orderId
                ]);
                $completed++;
            } catch (\Exception $e) {
                Log::error("Failed to complete order {$orderId}: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', "{$completed} order(s) completed successfully.");
    }

    /**
     * Bulk void orders
     */
    public function bulkVoid(Request $request) {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:device_orders,id'],
        ]);

        $voided = 0;
        foreach ($validated['ids'] as $id) {
            try {
                $deviceOrder = DeviceOrder::find($id);
                if ($deviceOrder) {
                    $deviceOrder->update(['status' => OrderStatus::VOIDED]);
                    $this->orderService->voidOrder($deviceOrder);
                    $voided++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to void order {$id}: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', "{$voided} order(s) voided successfully.");
    }
}
