<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreServiceRequest;
use App\Models\DeviceOrder;
use App\Models\ServiceRequest;
use App\Events\ServiceRequest\ServiceRequestNotification;
use App\Http\Resources\ServiceRequestResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ServiceRequestApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Sends a service request to the server 
     * and broadcasts the event
     *  
     */
    public function store(StoreServiceRequest $request)
    {
        $validatedData = $request->validated();

        $deviceOrder = DeviceOrder::where('order_id', $validatedData['order_id'])->first();

        if (! $deviceOrder) {
            return ApiResponse::notFound('Device order not found', ['order_id' => $validatedData['order_id']]);
        }

        // Ensure the requesting device owns the order (prevent cross-device requests)
        $device = $request->user();
        if ($device && $device->id !== $deviceOrder->device_id) {
            return ApiResponse::error('Unauthorized to request service for this order', null, 403);
        }

        try {
            DB::beginTransaction();

            $deviceOrder->serviceRequests()->create([
                'table_service_id' => $validatedData['table_service_id'],
                'order_id' => $validatedData['order_id'],
            ]);

            $serviceRequest = $deviceOrder->serviceRequests()->latest()->first();

            if (! $serviceRequest) {
                DB::rollBack();
                return ApiResponse::error('Service request could not be sent', null, 400);
            }

            // Broadcast to other listeners
            broadcast(new ServiceRequestNotification($serviceRequest))->toOthers();

            DB::commit();

            return ApiResponse::success([
                'service_request' => new ServiceRequestResource($serviceRequest),
            ], 'Service sent successfully', 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return ApiResponse::error('Failed to send service request', null, 500);
        }
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
