<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Http\Resources\DeviceResource;
use App\Http\Responses\ApiResponse;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use Illuminate\Http\Request;

class DeviceApiController extends Controller
{
    /**
     * Returns a list of all devices
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return DeviceResource::collection(Device::with('table')->get());
    }

    /**
     * Store a newly created device in storage.
     */
    public function store(StoreDeviceRequest $request)
    {
        $device = Device::create($request->validated());

        return (new DeviceResource($device->load('table')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified device.
     */
    public function show(Device $device)
    {
        return new DeviceResource($device->load('table'));
    }

    /**
     * Update the specified device.
     */
    public function update(UpdateDeviceRequest $request, Device $device)
    {
        $device->update($request->validated());

        return new DeviceResource($device->load('table'));
    }

    /**
     * Remove the specified device from storage.
     */
    public function destroy(Device $device)
    {
        $device->delete();

        return response()->json(['success' => true], 204);
    }

    /**
     * Find the device (and its table) by the authenticated device or by IP.
     *
     * Behaviour: If the request is authenticated as a device, use that device.
     * Otherwise, use the optional `ip_address` query param or the request IP
     * to find an active device record.
     *
     * Response: { success: true, device_id, table: { id, name } } or 404.
     */
    public function getTableByIp(Request $request)
    {
        // Accept POST body `ip`, query `ip` or fallback to request IP
        $ip = $request->input('ip') ?? $request->query('ip') ?? $request->ip();

        // Diagnostics
        $ipUsed = $ip;

        // If an IP was provided in the request, perform a lookup by IP first and
        // return 404 immediately if none is found (do not fall back to a different token).
        if ($request->filled('ip') || $request->query('ip')) {
            $device = Device::where('ip_address', $ip)->where('is_active', true)->with('table')->first();

            if (! $device) {
                return ApiResponse::notFound('Device not found', ['ip_used' => $ip]);
            }

        } else {
            // No IP provided — prefer the authenticated device (if any)
            $device = $request->user();

            // If middleware didn't resolve user (edge cases), try token lookup
            if (! $device) {
                $tokenString = $request->bearerToken();
                if ($tokenString) {
                    $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
                    if ($token && $token->tokenable) {
                        $device = $token->tokenable;
                    }
                }
            }
        }

        if (! $device) {
            return ApiResponse::notFound('Device not found', ['ip_used' => $ipUsed]);
        }

        // Prefer returning Resource classes; TableResource may or may not exist
        $table = $device->table ? new \App\Http\Resources\TableResource($device->table) : null;

        return ApiResponse::success([
            'device' => new DeviceResource($device->load('table')),
            'table' => $table,
            'ip_used' => $ipUsed,
        ]);
    }

}