<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\KryptonContextService;

use Inertia\Inertia;

// use App\Repositories\Krypton\OrderRepository;
// use App\Http\Resources\OrderResource;

use App\Models\Krypton\Order;
// use App\Models\Krypton\Table;

use App\Models\DeviceOrder;
// use App\Models\Krypton\TerminalSession;

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
        // $context = $kryptonContextService->getCurrentSessions();
        // $orders = OrderRepository::getAllOrdersWithDeviceData();
        // $orders = Order::with(['tableOrders','orderChecks', 'orderedMenus'])->whereDate('created_on', Carbon::yesterday())->get();

        $orders = DeviceOrder::with(['device'])
                // ->where('terminal_session_id', )
                ->get();

        return Inertia::render('Orders', [
            'title' => 'Orders',
            'description' => 'Daily Orders',    
            'orders' => $orders,
            'user' => auth()->user(),
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
