<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Http\Resources\DeviceResource;

class DeviceApiController extends Controller
{

  
    /**
     * Returns a list of all devices
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return DeviceResource::collection(Device::all());
    }

   
    /**
     * Display the specified device.
     *
     * @param  \App\Models\Device  $device
     * @return \Illuminate\Http\Response
     */
    public function show(Device $device)
    {
        return new DeviceResource($device->load(['table']));
    }

    public function heartbeat(Request $request) {
        
    }
}
