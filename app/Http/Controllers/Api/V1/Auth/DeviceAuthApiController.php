<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Device;
use App\Actions\Device\RegisterDevice;
use App\Http\Resources\DeviceResource;
use App\Http\Requests\DeviceRegisterRequest;
use App\Models\DeviceRegistrationCode;

class DeviceAuthApiController extends Controller
{
    
    /**
     * Register a device
     * 
     * @unauthenticated
     * 
     * @param DeviceRegisterRequest $request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(DeviceRegisterRequest $request)
    {
        $validated = $request->validated();
        $validated['ip_address'] = $request->ip(); // Capture the device's IP address
        $device = RegisterDevice::run($validated);
        
        // Create token with device info
        $token = $device->createToken(
            name: 'device-auth',           
            expiresAt: now()->addDays(7)
        )->plainTextToken;
        
        return response()->json([
            'token' => $token,
            'device' => $device,
            'expires_at' => now()->addDays(7)->toDateTimeString()
        ], 201);
    }


    /**
     * Login a device
     * 
     * @unauthenticated
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
           'device_uuid' => ['required', 'exists:devices,device_uuid'],
        ]);

        $device = Device::where('device_uuid', $validated['device_uuid'])->first();

        $device->update(['last_active_at' => now()]);

        // Revoke all existing tokens (optional)
        $device->tokens()->delete();

         // Create token with device info
        $token = $device->createToken(
            name: 'device-auth',
            // abilities: [
            //     'order:create', 
            //     'order:view', 
            //     'order:edit', 
            //     'order:delete', 
            //     'service_request:create',
            //     'menu:view',
            // ],
            expiresAt: now()->addDays(7)
        )->plainTextToken;
        
        return response()->json([
            'token' => $token,
            'device' => $device,
            'expires_at' => now()->addDays(7)->toDateTimeString()
        ]);


    }


    /**
     * Revoke the current token and issue a new one with the same
     * abilities and expiration time.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request) {

        $device = $request->user(); // Sanctum resolves the Device model

        // Revoke current token
        $device->currentAccessToken()->delete();
        
          // Create token with device info
        $newToken = $device->createToken(
            name: 'device-auth',
            abilities: [
                'order:create', 
                'order:view', 
                'order:edit', 
                'order:delete', 
                'service_request:create',
                'menu:view',
            ],
            expiresAt: now()->addDays(7)
        )->plainTextToken;
        
        return response()->json([
            'token' => $newToken,
            'expires_at' => now()->addDays(7)->toDateTimeString()
        ]);
    }

    /**
     * Revoke the token of the device that made the request and logout.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request) {

        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Successfully logged out'
        ]);

    }
}
