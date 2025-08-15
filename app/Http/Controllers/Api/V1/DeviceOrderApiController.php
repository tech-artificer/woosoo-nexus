<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreDeviceOrderRequest;
use App\Services\Krypton\OrderService;
use App\Http\Resources\DeviceOrderResource;
use App\Models\DeviceOrder;
use App\Events\Order\OrderCreated;
use App\Models\Device;

/**
 * Handle incoming order requests from devices.
 */
class DeviceOrderApiController extends Controller
{
    /**
     * The Order Service instance.
     *
     * @var OrderService
     */
    protected $orderService;

    /**
     * Create a new controller instance.
     *
     * @param  OrderService  $orderService
     * @return void
     */
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
        // Validate the incoming request
        $validatedData = $request->validated();

        // Initialize errors array
        $errors = [];

        // Get the device from the incoming request
        $device = $request->user();

        // Check if the device is assigned to a table
        if( $device && $device->table_id ) {
            // Check if the table is open
            if( !$this->orderService->checkIfTableIsOpen($device->table_id) ) {
                // Process the order
                $deviceOrder = $this->orderService->processOrder($device, $validatedData);

                // Return the order as a resource
                return response()->json([
                    'success' => true,
                    'order' => new DeviceOrderResource($deviceOrder)
                ], 201);
            } else {
                // Add error message to the errors array
                $errors[] = 'Seems like the table is already in use. Please try again later.';
            }
        } else {
            // Add error message to the errors array
            $errors[] = 'The device is not assigned to a table. Please assign the device to a table and try again.';
        }

        // Return the errors array
        return response()->json([
            'success' => false,
            'message' => 'Order processing failed.',
            'errors' => $errors,
        ], 500);
    }
}

