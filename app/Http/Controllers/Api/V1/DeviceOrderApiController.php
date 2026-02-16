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
use Illuminate\Support\Facades\DB;

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

        if (! $device || ! $device->table_id) {
            $errors[] = 'The device is not assigned to a table. Please assign the device to a table and try again.';

            return response()->json([
                'success' => false,
                'message' => 'Order processing failed.',
                'errors' => $errors,
            ], 500);
        }

        try {
            $result = DB::transaction(function () use ($device, $validatedData) {
                // Ensure there is no existing PENDING or CONFIRMED order for this device before creating a new one.
                $existing = $device->orders()
                    ->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::PENDING->value])
                    ->lockForUpdate()
                    ->latest()
                    ->first();

                if ($existing) {
                    return ['existing' => $existing];
                }

                $order = app(OrderService::class)->processOrder($device, $validatedData);

                return ['order' => $order];
            });

            if (isset($result['existing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'An existing order (pending or confirmed) prevents creating a new order for this device.',
                    'order' => new DeviceOrderResource($result['existing'])
                ], 409);
            }

            $order = $result['order'] ?? null;
            if (! $order) {
                $errors[] = 'Order creation failed unexpectedly.';

                return response()->json([
                    'success' => false,
                    'message' => 'Order processing failed.',
                    'errors' => $errors,
                ], 500);
            }

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
    
    }


}

