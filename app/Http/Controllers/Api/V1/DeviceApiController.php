<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Device;
use App\Http\Resources\DeviceResource;
use App\Http\Responses\ApiResponse;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Support\DeviceSecurityCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $branchId = $this->resolveBranchIdForDeviceCreate($request);

        if ($branchId === null) {
            return response()->json([
                'message' => 'No branch context is available for device creation.',
                'errors' => [
                    'branch' => 'Assign the current device to a branch or keep exactly one branch in this install.',
                ],
            ], 422);
        }

        $requestedSecurityCode = trim((string) ($data['security_code'] ?? ''));
        $isGeneratedCode = $requestedSecurityCode === '';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $plainSecurityCode = $isGeneratedCode
                ? $this->generateUniqueSecurityCode()
                : $requestedSecurityCode;

            try {
                $device = DB::transaction(function () use ($data, $branchId, $plainSecurityCode): Device {
                    if (DeviceSecurityCode::isAssigned($plainSecurityCode)) {
                        throw new \RuntimeException('security_code_assigned');
                    }

                    return Device::create(array_merge([
                        'name' => $data['name'],
                        'branch_id' => $branchId,
                        'ip_address' => $data['ip_address'],
                        'port' => $data['port'] ?? null,
                        'table_id' => $data['table_id'] ?? null,
                        'is_active' => true,
                    ], DeviceSecurityCode::attributesFor($plainSecurityCode)));
                }, 3);

                return response()->json([
                    'device' => (new DeviceResource($device->load('table')))->resolve(),
                    'security_code' => $plainSecurityCode,
                ], 201);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'security_code_assigned') {
                    if ($isGeneratedCode) {
                        continue;
                    }

                    return $this->securityCodeConflictResponse();
                }

                throw $e;
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    if ($isGeneratedCode) {
                        continue;
                    }

                    return response()->json([
                        'message' => 'Device conflicts with an existing record',
                        'errors' => [
                            'device' => 'A device with this IP, name, or security code already exists',
                        ],
                    ], 409);
                }

                throw $e;
            }
        }

        return response()->json([
            'message' => 'Unable to generate a unique security code. Please try again.',
            'errors' => [
                'security_code' => 'Could not generate a unique code',
            ],
        ], 409);
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
        $isTesting = app()->environment('testing') || env('APP_ENV') === 'testing';

        if ($request->filled('ip') || $request->query('ip')) {
            // In tests we avoid eager-loading the POS `table` relation which
            // would hit the external `pos` connection. Load only the device
            // record and leave `table` null for test assertions.
            $device = Device::where('ip_address', $ip)
                ->where('is_active', true)
                ->when(! $isTesting, fn($q) => $q->with('table'))
                ->first();

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

        // Prefer returning Resource classes; TableResource may or may not exist.
        // Avoid touching the POS `table` relation in tests (it may attempt
        // a connection to the external POS database which isn't available
        // in CI/test environments).
        $table = null;
        $deviceForResource = $device;
        if (! $isTesting) {
            $deviceForResource = $device->load('table');
            $table = $deviceForResource->table ? new \App\Http\Resources\TableResource($deviceForResource->table) : null;
        }

        return ApiResponse::success([
            'device' => new DeviceResource($deviceForResource),
            'table' => $table,
            'ip_used' => $ipUsed,
        ]);
    }

    private function securityCodeExists(string $plainSecurityCode): bool
    {
        return DeviceSecurityCode::isAssigned($plainSecurityCode);
    }

    private function generateUniqueSecurityCode(int $maxAttempts = 20): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = (string) random_int(100000, 999999);

            if (! $this->securityCodeExists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to generate a unique security code. Please try again.');
    }

    private function resolveBranchIdForDeviceCreate(Request $request): ?int
    {
        $device = $request->user();

        if ($device instanceof Device && $device->branch_id !== null) {
            return (int) $device->branch_id;
        }

        if (Branch::query()->count() === 1) {
            return (int) Branch::query()->value('id');
        }

        return null;
    }

    private function securityCodeConflictResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Security code already assigned to another device',
            'errors' => [
                'security_code' => 'This code is in use',
            ],
        ], 409);
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (string) ($e->errorInfo[1] ?? '');

        return $sqlState === '23000' || $driverCode === '1062' || $driverCode === '19';
    }

}
