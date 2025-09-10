<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDeviceOrderRequest;
use App\Http\Resources\DeviceOrderResource;
use App\Models\DeviceOrder;
use App\Events\Order\OrderCreated;
use App\Models\Device;
use App\Services\Krypton\OrderService;
use App\Services\BroadcastService;

/**
 * Handle incoming order requests from devices.
 */
class DeviceOrderApiController extends Controller
{
    /**
     * Handle the incoming order request from a specific device.
     *
     * @param  StoreDeviceOrderRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(StoreDeviceOrderRequest $request)
    {   
        // Validate the incoming request
        $validatedData = $request->validated();
        // Initialize errors array
        $errors = [];
        // Get the device from the incoming request
        $device = $request->user();

        if( $device && $device->table_id) {

            $canOrder = $device->orders()->latest()->first();
            
            if( !$canOrder ) {
                
                $order = app(OrderService::class)->processOrder($device, $validatedData);

                app(BroadcastService::class)->dispatchBroadcastJob(new OrderCreated($order));

                return response()->json([
                    'success' => true,
                    'order' => new DeviceOrderResource($order)
                ], 201);

            }
            $errors[] = 'There is already an order in progress for this device.';  
        }else{
            $errors[] = 'The device is not assigned to a table. Please assign the device to a table and try again.';
        }
        return response()->json([
            'success' => false,
            'message' => 'Order processing failed.',
            'errors' => $errors,
        ], 500);
    
    }
}

