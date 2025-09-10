<?php

namespace App\Http\Controllers\Api\V1\Krypton;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Krypton\OrderRepository;
use App\Http\Resources\OrderResource;
use App\Models\Krypton\Order;
use App\Models\Krypton\Session;

class OrderApiController extends Controller
{
  
    /**
     * Return a list of all orders with the corresponding device data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $sessionId = Session::fromQuery('CALL get_latest_session_id()')->first();
        // $orders = OrderRepository::getAllOrdersWithDeviceData();
        return OrderResource::collection(Order::latest('created_on')->where(['session_id' => $sessionId->id])->get());
    }
    // /**
    //  * Show the form for creating a new resource.
    //  */
    // public function create()
    // {
    //     //
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(Request $request)
    // {
    //     //
    // }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        return new OrderResource ($order);
    }

    // /**
    //  * Show the form for editing the specified resource.
    //  */
    // public function edit(string $id)
    // {
    //     //
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(string $id)
    // {
    //     //
    // }
}
