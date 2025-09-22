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
use Illuminate\Support\Facades\Auth;
use App\Enums\OrderStatus;

use App\Jobs\PrinterOrderJob;

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

            $canOrder = $device->orders()->whereIn('status', [OrderStatus::PENDING, OrderStatus::CONFIRMED])->latest()->first();
           
            if(  $canOrder ) {
                 return response()->json([
                    'success' => true,
                    'message' => 'Order already in progress',
                    'order' => new DeviceOrderResource($canOrder)
                ], 201);
            }
                
            $order = app(OrderService::class)->processOrder($device, $validatedData);

            app(BroadcastService::class)->dispatchBroadcastJob(new OrderCreated($order));

            return response()->json([
                'success' => true,
                'order' => new DeviceOrderResource($order),
                'print' => $this->printOrder($order)
            ], 201);

           
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

    public function printOrder(DeviceOrder $order)
    {
        // foreach ($request->items as $item) {
        //     $order->items()->create($item);
        // }

        
        // foreach ($order->items as $item) {
        //     $printer->text("{$item->qty}x {$item->name}\n");
        // }

        // Send to kitchen and bar printers
        PrinterOrderJob::dispatch($order, 'cashier')->onQueue('cashier');
        // PrinterOrderJob::dispatch($order->id, 'bar')->onQueue('bar');
        // If dessert applies
        // PrinterOrderJob::dispatch($order->id, 'dessert')->onQueue('dessert');

        return response()->json(['success' => true, 'order' => $order]);
    }
}

