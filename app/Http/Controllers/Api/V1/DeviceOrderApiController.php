<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDeviceOrderRequest;
use App\Services\Krypton\OrderService;
use App\Http\Resources\OrderResource;
use App\Events\Order\OrderCreated;
use App\Models\DeviceOrder;

class DeviceOrderApiController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Handle the incoming order request from a specific device.
     *
     * @param  StoreDeviceOrderRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(StoreDeviceOrderRequest $request)
    {   
        $validatedData = $request->validated();

        $device = $request->user();
        
        if ($device->table_id !== $validatedData['table_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Device is not assigned to any table.'
            ], 400);
        }

        $order = $this->orderService->processOrder($device, $validatedData);

        if ( !$order ) {
            return response()->json([
                'message' => 'Failed to create order.'
            ], 500);
        }

        // $deviceOrder = DeviceOrder::where('order_id', $order->id)->first();
        // broadcast(new OrderCreated($deviceOrder));
        // return $order;
        return $order;
    }
}

