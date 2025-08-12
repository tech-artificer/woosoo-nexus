<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreServiceRequest;
use App\Models\DeviceOrder;
use App\Models\ServiceRequest;
use App\Events\ServiceRequest\ServiceRequestNotification;

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

        $deviceOrder->serviceRequests()->create([
            'table_service_id' => $validatedData['table_service_id'],
            'order_id' => $validatedData['order_id'],
        ]);
              
        $serviceRequest = $deviceOrder->serviceRequests()->latest()->first();

        if( !$serviceRequest ) {
            return response()->json([
                'success' => false,
                'message' => 'Service request could not be sent'
            ], 400);
        }

        // $device = $request->user(); 
        // return response()->json([
        //     'success' => true,
        //     'message' => 'Service sent successfully',
        // ]);

        broadcast(new ServiceRequestNotification($serviceRequest))->toOthers();

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Service sent successfully'
        // ]);
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
