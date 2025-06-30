<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Inertia\Inertia;

// use App\Models\Krypton\Order;
// use App\Models\Krypton\Table;

// use App\Models\DeviceOrder;
// use App\Models\Krypton\TerminalSession;

use App\Repositories\Krypton\OrderRepository;

class OrderController extends Controller
{
   
    /**
     * Render the orders page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // $terminalSession = TerminalSession::current()->latest('created_on')->first() ?? false;

        // $orders = Order::where(['terminal_session_id' => $terminalSession->id])
        //                 ->with(['orderCheck', 'orderedMenus'])
        //                 ->latest('created_on')
        //                 ->get();

        // foreach ($orders as $order) {
            
        //     $order->deviceOrder = DeviceOrder::select(['order_number', 'status', 'device_id', 'table_id'])->with('device', 'table')->where([
        //         'order_id' => $order->id, 
        //         'terminal_session_id' => $order->terminal_session_id
        //     ])->first();

        // }

        return Inertia::render('Orders', [
            'title' => 'Orders',
            'description' => 'Daily Orders',
            'orders' => OrderRepository::getAllOrdersWithDeviceData(),
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
