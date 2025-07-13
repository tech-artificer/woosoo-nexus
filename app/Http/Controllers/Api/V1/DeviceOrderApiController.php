<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDeviceOrderRequest;
use App\Services\Krypton\OrderService;
use App\Http\Resources\DeviceOrderResource;
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

        $errors = [];
        $device = $request->user();

        if( $device->table_id ) {
            // Check if table is already open
            // if(  !$this->orderService->checkIfTableIsOpen($device->table_id) ) {
               
                $order = $this->orderService->processOrder($device, $validatedData);

                if ( $order ) {

                    $deviceOrder = DeviceOrder::where('order_id', $order->id)->first();
                    OrderCreated::dispatch($deviceOrder);
                    // broadcast(new OrderCreated($deviceOrder));

                    return new DeviceOrderResource($deviceOrder);
                }
            // }
            // $error = 'Table is already open';
        }else{
            $error= 'Device is not assigned to any table.';
        }
     
        return response()->json([
            'message' => 'Failed to create order.',
            'errors' => $error,
        ], 500);

       
    }
}

