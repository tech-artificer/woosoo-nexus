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
        $session = Session::fromQuery('CALL get_latest_session_id()')->first();

        $orders = DeviceOrder::with(['device', 'order', 'table', 'order', 'serviceRequests'])
                    ->where('session_id', $session->id)
                    ->whereIn('status', [OrderStatus::CONFIRMED, OrderStatus::PENDING])
                    ->orderBy('table_id', 'asc')
                    ->orderBy('created_at', 'desc')
                    ->activeOrder()
                    ->get();

        $orderHistory = DeviceOrder::with(['device', 'order', 'table', 'order', 'serviceRequests'])
                    ->where('session_id', $session->id)
                    ->whereIn('status', [OrderStatus::COMPLETED, OrderStatus::VOIDED, OrderStatus::CANCELLED])
                    ->orderBy('table_id', 'asc')
                    ->orderBy('created_at', 'desc')
                    ->get();
        
        return Inertia::render('Orders/Index', [
            'title' => 'Orders',
            'description' => 'Daily Orders',    
            'orders' => $orders,
            'orderHistory' => $orderHistory,
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
        $this->orderService->voidOrder($deviceOrder);
        $deviceOrder->update(['status' => OrderStatus::VOIDED]);

         //Run the console command
        $exitCode = Artisan::call('broadcast:order-voided', [
            'order_id' => $deviceOrder->order_id
        ]);

        // Optional: capture output
        $output = Artisan::output();

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
}
