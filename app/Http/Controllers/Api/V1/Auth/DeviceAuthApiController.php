<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Device;
// use App\Http\Resources\DeviceResource;
use App\Http\Requests\DeviceRegisterRequest;
use App\Services\AuditLogService;
use App\Helpers\BroadcastConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    protected function ipToLong(?string $ip): int|false
    {
        return $ip ? ip2long($ip) : false;
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = array_pad(explode('/', $cidr, 2), 2, '32');
        $ipLong = $this->ipToLong($ip);
        $subnetLong = $this->ipToLong($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskBits = max(0, min(32, (int) $mask));
        $maskLong = $maskBits === 0 ? 0 : (~0 << (32 - $maskBits));

        return (($ipLong & $maskLong) === ($subnetLong & $maskLong));
    }

    protected function same24(?string $a, ?string $b): bool
    {
        if (! $a || ! $b) return false;
        $partsA = explode('.', $a);
        $partsB = explode('.', $b);
        if (count($partsA) !== 4 || count($partsB) !== 4) return false;
        return $partsA[0] === $partsB[0] && $partsA[1] === $partsB[1] && $partsA[2] === $partsB[2];
    }

    protected function shouldTrustClientSuppliedIp(?string $clientSupplied, ?string $requestIp): bool
    {
        if (! $clientSupplied || ! $this->isPrivateIp($clientSupplied)) {
            return false;
        }

        $enabled = filter_var(env('DEVICE_ALLOW_CLIENT_SUPPLIED_IP', false), FILTER_VALIDATE_BOOL);
        if (! $enabled) {
            return false;
        }

        $raw = trim((string) env('DEVICE_ALLOWED_PRIVATE_SUBNETS', ''));
        if ($raw !== '') {
            $subnets = array_filter(array_map('trim', explode(',', $raw)));
            foreach ($subnets as $cidr) {
                if ($this->ipInCidr($clientSupplied, $cidr)) {
                    return true;
                }
            }
            return false;
        }

        // Safe fallback: only trust when request IP is private and in same /24.
        return $this->isPrivateIp($requestIp) && $this->same24($clientSupplied, $requestIp);
    }
    /**
     * Register a device
     * 
     * Batch 1: Security-code-first registration flow
     * 
     * Match-count logic:
     * - 0 matches: Create new device and return token (200)
     * - 1 match: Claim device, update ip_address/last_seen_at, return token (200)
     * - 2+ matches: Ambiguous state, reject with 409 Conflict
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
        $securityCode = $validated['security_code'] ?? ($validated['code'] ?? null);

        // IP address resolution: prefer client-supplied private IP,
        // fall back to request IP, then null
        $clientSupplied = $request->input('ip_address');
        $requestIp = $request->ip();

        $ipToUse = null;
        if ($this->shouldTrustClientSuppliedIp($clientSupplied, $requestIp)) {
            $ipToUse = $clientSupplied;
        } elseif ($this->isPrivateIp($requestIp)) {
            $ipToUse = $requestIp;
        } else {
            $ipToUse = $requestIp;
        }

        if (! $securityCode) {
            return response()->json([
                'success' => false,
                'message' => 'Security code is required',
                'ip_used' => $ipToUse,
                'broadcasting' => BroadcastConfig::clientPayload(),
            ], 422);
        }

        $matchingDeviceIds = Device::active()
            ->whereNotNull('security_code')
            ->get(['id', 'security_code'])
            ->filter(fn (Device $device) => Hash::check($securityCode, $device->security_code))
            ->pluck('id')
            ->values();

        $matchCount = $matchingDeviceIds->count();

        if ($matchCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid security code',
                'ip_used' => $ipToUse,
                'broadcasting' => BroadcastConfig::clientPayload(),
            ], 422);
        }

        if ($matchCount > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Ambiguous registration: multiple devices with this security code',
                'match_count' => $matchCount,
                'ip_used' => $ipToUse,
                'broadcasting' => BroadcastConfig::clientPayload(),
            ], 409);
        }

        $deviceId = (int) $matchingDeviceIds->first();

        $token = DB::transaction(function () use ($deviceId, $ipToUse) {
            /** @var Device $device */
            $device = Device::whereKey($deviceId)->lockForUpdate()->firstOrFail();

            $device->update([
                'ip_address' => $ipToUse,
                'last_ip_address' => $ipToUse,
                'last_seen_at' => now(),
            ]);

            // Keep active sessions and remove only expired tokens.
            $device->tokens()->where('expires_at', '<', now())->delete();

            return $device->createToken(
                name: 'device-auth',
                expiresAt: now()->addDays(30)
            )->plainTextToken;
        });

        $device = Device::findOrFail($deviceId);

        AuditLogService::deviceRegistered($request, $device->id);

        return response()->json([
            'success' => true,
            'token' => $token,
            'device' => $device,
            'table' => $device->table()->first(['id', 'name']),
            'expires_at' => now()->addDays(30)->toDateTimeString(),
            'ip_used' => $ipToUse,
            'broadcasting' => BroadcastConfig::clientPayload(),
        ], 200);
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

        if ($this->shouldTrustClientSuppliedIp($clientSupplied, $requestIp)) {
            $ip = $clientSupplied;
        } else {
            $ip = $requestIp;
        }

        $device = Device::where(['ip_address' => $ip, 'is_active' => true])->first();

        if(  !$device ) {
            AuditLogService::authFailed($request, 'device_not_found_or_inactive');

            return response()->json([
                'success' => false,
                'error' => 'Device not found',
                'ip_address' => $ip
            ], 404);
        }

        $device->update([
            'last_seen_at' => now(),
            'last_ip_address' => $ip,
        ]);
        // H3 fix 2026-04-08: only revoke expired tokens so concurrent device connections
        // (e.g., print bridge) are not disconnected when a tablet re-authenticates via IP.
        $device->tokens()->where('expires_at', '<', now())->delete();

         // Create token with device info
        $token = $device->createToken(
            name: 'device-auth',
            expiresAt: now()->addDays(30)
        )->plainTextToken;
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'device' => $device,
            'table' => $device->table()->first(['id', 'name']),
            'expires_at' => now()->addDays(30)->toDateTimeString(),
            'ip_used' => $ip,
            'broadcasting' => BroadcastConfig::clientPayload(),
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
            'broadcasting' => BroadcastConfig::clientPayload(),
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
        // Prefer client-supplied private IP only when the same trust gate used by registration allows it.
        $clientSupplied = $request->input('ip_address');
        $requestIp = $request->ip();

        if ($this->shouldTrustClientSuppliedIp($clientSupplied, $requestIp)) {
            $ip = $clientSupplied;
        } elseif ($this->isPrivateIp($requestIp)) {
            $ip = $requestIp;
        } else {
            $ip = $requestIp;
        }

        $device = Device::where('ip_address', $ip)->where('is_active', true)->first();

        if (! $device) {
            return response()->json([
                'found' => false,
                'ip_used' => $ip,
                'broadcasting' => BroadcastConfig::clientPayload(),
            ], 200);
        }

        // Update last seen and IP tracking
        $device->update([
            'last_seen_at' => now(),
            'last_ip_address' => $ip,
        ]);

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
            'broadcasting' => BroadcastConfig::clientPayload(),
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
