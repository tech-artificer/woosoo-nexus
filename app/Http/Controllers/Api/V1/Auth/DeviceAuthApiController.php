<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Helpers\BroadcastConfig;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRegisterRequest;
use App\Models\Device;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceAuthApiController extends Controller
{
    // Utility: check for private/local IPv4 (10.*, 192.168.*, 172.16-31.*, 169.254.*)
    protected function isPrivateIp(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (str_starts_with($ip, '10.')) {
            return true;
        }

        if (str_starts_with($ip, '192.168.')) {
            return true;
        }

        if (preg_match('/^172\\.(1[6-9]|2[0-9]|3[0-1])\\./', $ip)) {
            return true;
        }

        return str_starts_with($ip, '169.254.');
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
        if (! $a || ! $b) {
            return false;
        }

        $partsA = explode('.', $a);
        $partsB = explode('.', $b);

        if (count($partsA) !== 4 || count($partsB) !== 4) {
            return false;
        }

        return $partsA[0] === $partsB[0]
            && $partsA[1] === $partsB[1]
            && $partsA[2] === $partsB[2];
    }

    protected function shouldTrustClientSuppliedIp(?string $clientSupplied, ?string $requestIp): bool
    {
        if (! $clientSupplied || ! $this->isPrivateIp($clientSupplied)) {
            return false;
        }

        $enabled = filter_var(config('device.allow_client_supplied_ip', false), FILTER_VALIDATE_BOOL);
        if (! $enabled) {
            return false;
        }

        $raw = trim((string) config('device.allowed_private_subnets', ''));
        if ($raw !== '') {
            $subnets = array_filter(array_map('trim', explode(',', $raw)));

            foreach ($subnets as $cidr) {
                if ($this->ipInCidr($clientSupplied, $cidr)) {
                    return true;
                }
            }

            return false;
        }

        return $this->isPrivateIp($requestIp) && $this->same24($clientSupplied, $requestIp);
    }

    protected function resolveClientSuppliedIp(Request $request): ?string
    {
        // Primary contract: ip_address
        // Legacy compatibility: ip
        return $request->input('ip_address') ?: $request->input('ip');
    }

    /**
     * Register a device.
     *
     * Global passcode registration flow.
     *
     * The caller provides a shared passcode from config('device.auth_passcode').
     * On success the device is created (new IP) or reused (existing IP).
     */
    public function register(DeviceRegisterRequest $request)
    {
        $validated = $request->validated();

        // Accept passcode, or legacy aliases security_code / code.
        $passcode = $validated['passcode'] ?? ($validated['security_code'] ?? ($validated['code'] ?? null));

        // In passcode-authenticated flows the client-supplied ip_address is the
        // device identity, so trust it directly and fall back to request IP.
        $ipToUse = $this->resolveClientSuppliedIp($request) ?: $request->ip();

        $configuredPasscode = config('device.auth_passcode');
        if (! $configuredPasscode || $passcode !== $configuredPasscode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid passcode',
                'ip_used' => $ipToUse,
                'broadcasting' => BroadcastConfig::clientPayload(),
            ], 422);
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $registration = DB::transaction(function () use ($validated, $ipToUse): array {
                    /** @var Device|null $device */
                    $device = Device::withTrashed()
                        ->where('ip_address', $ipToUse)
                        ->lockForUpdate()
                        ->first();

                    if ($device && ($device->trashed() || ! $device->is_active)) {
                        return [
                            'status' => 409,
                            'payload' => [
                                'success' => false,
                                'message' => 'Device IP is registered but inactive. Reactivate it before registering.',
                                'ip_used' => $ipToUse,
                                'broadcasting' => BroadcastConfig::clientPayload(),
                            ],
                        ];
                    }

                    if (! $device) {
                        $nameConflict = Device::withTrashed()
                            ->where('name', $validated['name'])
                            ->lockForUpdate()
                            ->first();

                        if ($nameConflict) {
                            return [
                                'status' => 409,
                                'payload' => [
                                    'success' => false,
                                    'message' => 'Device name is already registered.',
                                    'ip_used' => $ipToUse,
                                    'broadcasting' => BroadcastConfig::clientPayload(),
                                ],
                            ];
                        }

                        $device = Device::create([
                            'name' => $validated['name'],
                            'type' => 'tablet',
                            'ip_address' => $ipToUse,
                            'is_active' => true,
                            'last_seen_at' => now(),
                        ]);
                    }

                    $device->update([
                        'ip_address' => $ipToUse,
                        'last_ip_address' => $ipToUse,
                        'last_seen_at' => now(),
                    ]);

                    $device->tokens()->where('expires_at', '<', now())->delete();

                    $token = $device->createToken(
                        name: 'device-auth',
                        expiresAt: now()->addDays(30)
                    )->plainTextToken;

                    return [
                        'status' => 200,
                        'device_id' => $device->id,
                        'token' => $token,
                    ];
                }, 3);

                if (($registration['status'] ?? 200) !== 200) {
                    return response()->json($registration['payload'], $registration['status']);
                }

                $device = Device::findOrFail($registration['device_id']);
                $token = $registration['token'];
                break;
            } catch (QueryException $e) {
                if ($attempt === 0 && $this->isUniqueConstraintViolation($e)) {
                    continue;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Device registration conflicts with an existing device.',
                    'ip_used' => $ipToUse,
                    'broadcasting' => BroadcastConfig::clientPayload(),
                ], 409);
            }
        }

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
     * Login a device.
     *
     * When a passcode is supplied it is validated against config('device.auth_passcode')
     * and the client-supplied ip_address is used as the device identity directly.
     * When no passcode is supplied the legacy IP-trust path is preserved for
     * backward compatibility with tablets not yet sending the passcode field.
     */
    public function authenticate(Request $request)
    {
        $passcode = $request->input('passcode');

        if ($passcode !== null) {
            $configuredPasscode = config('device.auth_passcode');
            if (! $configuredPasscode || $passcode !== $configuredPasscode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid passcode',
                ], 422);
            }

            $ip = $this->resolveClientSuppliedIp($request) ?: $request->ip();
        } else {
            $clientSupplied = $this->resolveClientSuppliedIp($request);
            $requestIp = $request->ip();

            if ($this->shouldTrustClientSuppliedIp($clientSupplied, $requestIp)) {
                $ip = $clientSupplied;
            } else {
                $ip = $requestIp;
            }
        }

        $device = Device::where(['ip_address' => $ip, 'is_active' => true])->first();

        if (! $device) {
            AuditLogService::authFailed($request, 'device_not_found_or_inactive');

            return response()->json([
                'success' => false,
                'error' => 'Device not found',
                'ip_address' => $ip,
            ], 404);
        }

        $device->update([
            'last_seen_at' => now(),
            'last_ip_address' => $ip,
        ]);

        $device->tokens()->where('expires_at', '<', now())->delete();

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
     */
    public function refresh(Request $request)
    {
        $device = $request->user();

        $currentToken = $request->user()?->currentAccessToken();
        if ($currentToken) {
            $request->user()->tokens()->where('id', $currentToken->id)->delete();
        }

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
     */
    public function logout(Request $request)
    {
        $current = $request->user()?->currentAccessToken();
        if ($current) {
            $request->user()->tokens()->where('id', $current->id)->delete();
        }

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * Look up a device by the request IP and issue a short-lived token.
     *
     * Called by the print-bridge on startup (GET /api/device/lookup-by-ip).
     * No authentication required - the device is identified purely by IP.
     *
     * Response shape the print-bridge expects:
     *   { found: true,  device: { device_id, auth_token, printer_name, bluetooth_address } }
     *   { found: false }
     */
    public function lookupByIp(Request $request)
    {
        $clientSupplied = $this->resolveClientSuppliedIp($request);
        $requestIp = $request->ip();

        if ($this->shouldTrustClientSuppliedIp($clientSupplied, $requestIp)) {
            $ip = $clientSupplied;
        } elseif ($this->isPrivateIp($requestIp)) {
            $ip = $requestIp;
        } else {
            $ip = $requestIp;
        }

        $lookup = DB::transaction(function () use ($ip): ?array {
            /** @var Device|null $device */
            $device = Device::where('ip_address', $ip)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $device) {
                return null;
            }

            $device->update([
                'last_seen_at' => now(),
                'last_ip_address' => $ip,
            ]);

            $device->tokens()->where('name', 'device-auth')->delete();

            $token = $device->createToken(
                name: 'device-auth',
                expiresAt: now()->addDays(7)
            )->plainTextToken;

            return [
                'device_id' => (string) $device->id,
                'auth_token' => $token,
            ];
        }, 3);

        if (! $lookup) {
            return response()->json([
                'found' => false,
                'ip_used' => $ip,
                'broadcasting' => BroadcastConfig::clientPayload(),
            ], 200);
        }

        return response()->json([
            'found' => true,
            'device' => [
                'device_id' => $lookup['device_id'],
                'auth_token' => $lookup['auth_token'],
                'printer_name' => null,
                'bluetooth_address' => null,
            ],
            'ip_used' => $ip,
            'broadcasting' => BroadcastConfig::clientPayload(),
        ]);
    }

    /**
     * Verify a bearer token and return its validity and associated device.
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

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return $sqlState === '23000' || $driverCode === '1062' || $driverCode === '19';
    }
}
