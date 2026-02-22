<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Device;
use App\Actions\Device\RegisterDevice;
// use App\Http\Resources\DeviceResource;
use App\Http\Requests\DeviceRegisterRequest;
use App\Models\DeviceRegistrationCode;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceAuthApiController extends Controller
{
    // Utility: check for private/local IPv4 (10.*, 192.168.*, 172.16-31.*, 169.254.*)
    protected function isPrivateIp(?string $ip): bool
    {
        if (!$ip) return false;
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;

        // Private ranges
        if (str_starts_with($ip, '10.')) return true;
        if (str_starts_with($ip, '192.168.')) return true;
        if (preg_match('/^172\\.(1[6-9]|2[0-9]|3[0-1])\\./', $ip)) return true;
        if (str_starts_with($ip, '169.254.')) return true;

        return false;
    }
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

        // Prefer client-supplied ip_address when it looks like a LAN/private IP.
        $clientSupplied = $request->input('ip_address');
        $requestIp = $request->ip();

        $ipToUse = null;
        if ($clientSupplied && $this->isPrivateIp($clientSupplied)) {
            $ipToUse = $clientSupplied;
        } elseif ($this->isPrivateIp($requestIp)) {
            $ipToUse = $requestIp;
        } else {
            // If neither is private, prefer request ip (still usable), or null
            $ipToUse = $requestIp;
        }

        // Attach ip_address for RegisterDevice action
        $validated['ip_address'] = $ipToUse;

        // Avoid accidental collisions: if code provided, prefer code-based uniqueness.
        // The `code` column lives in `device_registration_codes` table — lookup that
        // table and if the code has already been claimed (used_by_device_id) return
        // the owning device as an existing registration.
        if (! empty($validated['code'])) {
            $codeRow = DeviceRegistrationCode::where('code', $validated['code'])->first();
            if ($codeRow && ! empty($codeRow->used_by_device_id)) {
                $existing = Device::find($codeRow->used_by_device_id);
            } else {
                $existing = null;
            }
        } else {
            $existing = null;
        }

        if (!$existing && $ipToUse) {
            $existing = Device::where('ip_address', $ipToUse)->first();
        }

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Device already registered',
                'device' => $existing,
                'ip_used' => $ipToUse,
            ], 409);
        }

        $device = RegisterDevice::run($validated);

        $token = $device->createToken(
            name: 'device-auth',
            expiresAt: now()->addDays(7)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'device' => $device,
            'table' => $device->table()->first(['id', 'name']),
            'expires_at' => now()->addDays(7)->toDateTimeString(),
            'ip_used' => $ipToUse,
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
        // Prefer client-supplied ip_address when private, otherwise request->ip()
        $clientSupplied = $request->input('ip_address');
        $requestIp = $request->ip();

        if ($clientSupplied && $this->isPrivateIp($clientSupplied)) {
            $ip = $clientSupplied;
        } else {
            $ip = $requestIp;
        }

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
            expiresAt: now()->addDays(7)
        )->plainTextToken;
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'device' => $device,
            'table' => $device->table()->first(['id', 'name']),
            'expires_at' => now()->addDays(7)->toDateTimeString(),
            'ip_used' => $ip,
        ]);
    }

    /**
     * Revoke the current token and issue a new one with the same
     * abilities and expiration time.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        $device = $request->user(); // Sanctum resolves Device model

        // Revoke current token safely
        // Delete the current access token via the tokens relationship to appease static analyzers
        $currentToken = $request->user()?->currentAccessToken();
        if ($currentToken) {
            $request->user()->tokens()->where('id', $currentToken->id)->delete();
        }

        // Create new token (with 7 days expiry for parity with register/auth)
        $newToken = $device->createToken(
            name: 'device-auth',
            expiresAt: now()->addDays(7)
        )->plainTextToken;

        $expiresAt = now()->addDays(7);

        return response()->json([
            'success' => true,
            'token' => $newToken,
            'device' => $device,
            'table' => $device->table()->first(['id', 'name']),
            'expires_at' => $expiresAt->toDateTimeString(),
        ]);
}


    /**
     * Revoke the token of the device that made the request and logout.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request) {
        // Delete current access token via relation (avoid static analyzer warning on token model)
        $current = $request->user()?->currentAccessToken();
        if ($current) {
            $request->user()->tokens()->where('id', $current->id)->delete();
        }

        return response()->json([
            'message' => 'Successfully logged out'
        ]);

    }

    /**
     * Look up a device by the request IP and issue a short-lived token.
     *
     * Called by the print-bridge on startup (GET /api/device/lookup-by-ip).
     * No authentication required — the device is identified purely by IP.
     *
     * Response shape the print-bridge expects:
     *   { found: true,  device: { device_id, auth_token, printer_name, bluetooth_address } }
     *   { found: false }
     *
     * @unauthenticated
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function lookupByIp(Request $request)
    {
        // Prefer client-supplied private IP, otherwise use request IP
        $clientSupplied = $request->input('ip_address');
        $requestIp = $request->ip();

        if ($clientSupplied && $this->isPrivateIp($clientSupplied)) {
            $ip = $clientSupplied;
        } elseif ($this->isPrivateIp($requestIp)) {
            $ip = $requestIp;
        } else {
            $ip = $requestIp;
        }

        $device = Device::where('ip_address', $ip)->where('is_active', true)->first();

        if (! $device) {
            return response()->json(['found' => false, 'ip_used' => $ip], 200);
        }

        // Revoke stale tokens to keep the token table tidy, then issue a fresh one
        $device->tokens()->where('name', 'device-auth')->delete();

        $token = $device->createToken(
            name: 'device-auth',
            expiresAt: now()->addDays(7)
        )->plainTextToken;

        return response()->json([
            'found'  => true,
            'device' => [
                'device_id'         => (string) $device->id,
                'auth_token'        => $token,
                'printer_name'      => null,
                'bluetooth_address' => null,
            ],
            'ip_used' => $ip,
        ]);
    }

    /**
     * Verify a bearer token and return its validity and associated device.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyToken(Request $request)
    {
        $tokenString = $request->bearerToken();

        if (! $tokenString) {
            return response()->json([
                'valid' => false,
                'message' => 'No bearer token provided.',
            ], 400);
        }

        $token = PersonalAccessToken::findToken($tokenString);

        if (! $token || ! $token->tokenable) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or revoked token.',
            ], 401);
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            return response()->json([
                'valid' => false,
                'message' => 'Token expired.',
            ], 401);
        }

        return response()->json([
            'valid' => true,
            'device' => $token->tokenable->only(['id', 'name']),
            'created_at' => $token->created_at,
            'expires_at' => $token->expires_at ?? null,
        ]);
    }
}
