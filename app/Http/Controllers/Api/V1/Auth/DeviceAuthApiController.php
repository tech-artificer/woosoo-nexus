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

        $device = Device::where(['ip_address' => $validated['ip_address']])->first();

        if( $device ) {
            return response()->json([
                'success' => false,
                'message' => 'Device already registered'
            ]);
        }

        $device = RegisterDevice::run($validated);
        
        // Create token with device info
        $token = $device->createToken(
            name: 'device-auth',           
            expiresAt: now()->addDays(7)
        )->plainTextToken;
        
        return response()->json([
            'success' => true,
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
    public function authenticate(Request $request)
    {
        // $validated = $request->validate([
        //    'device_uuid' => ['nullable'],
        //    'ip_address' => ['nullable', 'string', 'max:255'],
        // ]);

        $ip = $request->ip();
       
        $device = Device::where(['ip_address' => $ip, 'is_active' => true])->first();

        if(  !$device ) {
            return response()->json([
                'success' => false,
                'error' => 'Device not found',
                'ip_address' => $ip
            ], 404);
        }

        $device->update(['last_seen_at' => now()]);

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
            'success' => true,
            'token' => $token,
            'device' => $device,
            'table' => $device->table()->get(['id', 'name']),
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
            'token' => $newToken,
            'expires_at' => now()->addDays(7)->toDateTimeString(),
            'table' => $device->table()->get(['id', 'name']),
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
