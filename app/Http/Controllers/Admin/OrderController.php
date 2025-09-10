<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Krypton\KryptonContextService;

use Inertia\Inertia;

use App\Repositories\Krypton\OrderRepository;
use App\Repositories\Krypton\TableRepository;
// use App\Http\Resources\OrderResource;

use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
// use App\Models\Krypton\Table;

use App\Models\DeviceOrder;
use App\Enums\OrderStatus;
use App\Models\Krypton\Session;

use Carbon\Carbon;
class OrderController extends Controller
{
   
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
                    ->orderBy('table_id', 'asc')
                    ->orderBy('created_at', 'desc')
                    ->activeOrder()
                    ->get();
        
                   
        
        return Inertia::render('Orders', [
            'title' => 'Orders',
            'description' => 'Daily Orders',    
            'orders' => $orders,
            'user' => auth()->user(),
            'tableOrders' => $activeOrders,
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
    public function destroy(string $id)
    {
        // 
    }
}
