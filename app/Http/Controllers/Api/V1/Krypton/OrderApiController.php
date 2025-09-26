<?php

namespace App\Http\Controllers\Api\V1\Krypton;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Krypton\OrderRepository;
use App\Http\Resources\OrderResource;
use App\Models\Krypton\Order;
use App\Models\Krypton\Session;
use Illuminate\Support\Facades\Artisan;

class OrderApiController extends Controller
{
  
    /**
     * Return a list of all orders with the corresponding device data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // $sessionId = Session::fromQuery('CALL get_latest_session_id()')->first();
        // // $orders = OrderRepository::getAllOrdersWithDeviceData();
        // return OrderResource::collection(Order::latest('created_on')->where(['session_id' => $sessionId->id])->get());
    }
    
    // public function completeOrder(Request $request) {

    //     $orderId = $request->input('order_id');

    //     // Run the console command
    //     $exitCode = Artisan::call('broadcast:order-completed', [
    //         'order_id' => $orderId
    //     ]);

    //     // Optional: capture output
    //     $output = Artisan::output();

    //     return response()->json([
    //         'status' => 'Order updated and broadcasted',
    //         'output' => $output,
    //         'exitCode' => $exitCode
    //     ]);

    // }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {   
        return new OrderResource ($order);
    }
}
