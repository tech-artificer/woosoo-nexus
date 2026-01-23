<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceOrderRequest;
use App\Http\Resources\DeviceOrderResource;
use App\Models\DeviceOrder;
use App\Events\Order\OrderCreated;
use App\Services\Krypton\OrderService;
use App\Exceptions\SessionNotFoundException;
use App\Enums\OrderStatus;

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

            // Ensure there is no existing PENDING or CONFIRMED order for this device before creating a new one.
            $existing = $device->orders()->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::PENDING->value])->lockForUpdate()->latest()->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'An existing order (pending or confirmed) prevents creating a new order for this device.',
                    'order' => new DeviceOrderResource($existing)
                ], 409);
            }

            try {
                $order = app(OrderService::class)->processOrder($device, $validatedData);

                OrderCreated::dispatch($order);

                return response()->json([
                    'success' => true,
                    'order' => new DeviceOrderResource($order),
                ], 201);
            } catch (SessionNotFoundException $e) {
                // Transaction aborted: No active POS session
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'code' => 'SESSION_NOT_FOUND',
                ], 503);
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

