<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\OrderStatus;
use App\Events\Order\OrderCreated;
use App\Exceptions\MenuItemUnavailableException;
use App\Exceptions\SessionNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceOrderRequest;
use App\Http\Resources\DeviceOrderResource;
use App\Models\DeviceOrder;
use App\Models\Package;
use App\Services\AuditLogService;
use App\Services\Krypton\OrderService;
use InvalidArgumentException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handle incoming order requests from devices.
 */
class DeviceOrderApiController extends Controller
{
    private const POS_SQLSTATE_GENERAL_ERROR = 'HY000';

    private const POS_CONNECTION_REFUSED_ERROR_CODE = 2002;

    /**
     * Handle the incoming order request from a specific device.
     *
     * @return Response
     */
    public function __invoke(StoreDeviceOrderRequest $request)
    {
        // ── Idempotency ────────────────────────────────────────────────────
        // If the client sends X-Idempotency-Key, replay the cached response on
        // duplicate submissions (e.g. PWA retry after network error).
        // Requires CACHE_DRIVER=redis in production for atomic locking.
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));
        $idempotencyScope = null;
        $processingKey = null;
        $responseCacheKey = null;

        if ($idempotencyKey !== '') {
            $device = $request->user();
            $deviceId = $device && isset($device->id) ? (string) $device->id : 'anonymous';
            $idempotencyScope = 'device-order:'.$deviceId.':'.sha1($idempotencyKey);
            $processingKey = $idempotencyScope.':processing';
            $responseCacheKey = $idempotencyScope.':response';

            // Return cached response for duplicate submission (HTTP 200 replay)
            $cachedResponse = Cache::get($responseCacheKey);
            if (is_array($cachedResponse)) {
                return response()->json(
                    $cachedResponse['body'] ?? ['success' => true],
                    (int) ($cachedResponse['status'] ?? 200),
                    ['X-Idempotent-Replay' => 'true']
                );
            }

            // Block duplicate in-flight requests with the same key
            if (! Cache::add($processingKey, 1, now()->addSeconds(30))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate order request is already being processed',
                ], 409);
            }
        }

        // Validate the incoming request
        $validatedData = $request->validated();

        // Initialize errors array
        $errors = [];
        // Get the device from the incoming request
        $device = $request->user();

        if (! $device || ! $device->table_id) {
            $errors[] = 'The device is not assigned to a table. Please assign the device to a table and try again.';

            if ($processingKey !== null) {
                Cache::forget($processingKey);
            }

            return response()->json([
                'success' => false,
                'message' => 'Order processing failed.',
                'errors' => $errors,
            ], 500);
        }

        try {
            $result = DB::transaction(function () use ($device, $validatedData) {
                // Ensure there is no existing PENDING or CONFIRMED order for this device before creating a new one.
                $existing = $device->orders()
                    ->with(['items', 'device'])
                    ->whereIn('status', [OrderStatus::CONFIRMED->value, OrderStatus::PENDING->value])
                    ->lockForUpdate()
                    ->latest()
                    ->first();

                if ($existing) {
                    return ['existing' => $existing];
                }

                $order = app(OrderService::class)->processOrder($device, $this->expandIntentPayload($validatedData));

                return ['order' => $order];
            });

            if (isset($result['existing'])) {
                if ($processingKey !== null) {
                    Cache::forget($processingKey);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'An existing order (pending or confirmed) prevents creating a new order for this device.',
                    'order' => new DeviceOrderResource($result['existing']),
                ], 409);
            }

            $order = $result['order'] ?? null;
            if (! $order) {
                $errors[] = 'Order creation failed unexpectedly.';

                if ($processingKey !== null) {
                    Cache::forget($processingKey);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Order processing failed.',
                    'errors' => $errors,
                ], 500);
            }

            $this->dispatchOrderCreated($order, $device?->id);
            AuditLogService::orderStatusChanged($request, $order->id, 'NEW', OrderStatus::CONFIRMED->value, $device->id);

            // H4 fix 2026-04-08: eager-load relationships so DeviceOrderResource
            // returns items and device in the 201 response (prevents silent empty-items body).
            $order->load(['items', 'device']);

            $responseBody = [
                'success' => true,
                'order' => (new DeviceOrderResource($order))->toArray($request),
            ];

            // Cache the response for idempotency replay (24 hours TTL)
            if ($responseCacheKey !== null) {
                Cache::put($responseCacheKey, [
                    'body' => $responseBody,
                    'status' => 201,
                ], now()->addHours(24));
                Cache::forget($processingKey);
            }

            return response()->json($responseBody, 201);
        } catch (InvalidArgumentException $e) {
            Log::warning('Order creation rejected due to invalid initial payload', [
                'device_id' => $device?->id,
                'error' => $e->getMessage(),
            ]);

            if ($processingKey !== null) {
                Cache::forget($processingKey);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (MenuItemUnavailableException $e) {
            Log::warning('Order creation failed: menu item unavailable', [
                'device_id' => $device?->id,
                'error' => $e->getMessage(),
            ]);

            if ($processingKey !== null) {
                Cache::forget($processingKey);
            }

            return response()->json([
                'success' => false,
                'message' => 'Some menu items are no longer available. We refreshed the menu. Please review your order again.',
                'code' => ApiErrorCode::MENU_ITEM_UNAVAILABLE->value,
            ], 422);
        } catch (SessionNotFoundException $e) {
            // Transaction aborted: No active POS session
            if ($processingKey !== null) {
                Cache::forget($processingKey);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => ApiErrorCode::SESSION_NOT_FOUND->value,
            ], 503);
        } catch (QueryException $e) {
            Log::error('Order creation failed', [
                'device_id' => $device?->id,
                'error' => $e->getMessage(),
            ]);

            if ($processingKey !== null) {
                Cache::forget($processingKey);
            }

            if ($this->isPosServiceUnavailable($e)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order service is temporarily unavailable.',
                ], 503);
            }

            return response()->json([
                'success' => false,
                'message' => 'Order creation failed.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Order creation failed', [
                'device_id' => $device?->id,
                'error' => $e->getMessage(),
            ]);

            if ($processingKey !== null) {
                Cache::forget($processingKey);
            }

            return response()->json([
                'success' => false,
                'message' => 'Order creation failed.',
            ], 500);
        }

    }

    /**
     * Transform the tablet's intent-only payload into the internal package-with-modifiers
     * structure that OrderService::processOrder() and CreateOrderedMenu understand.
     *
     * Tablet sends:  { guest_count, package_id, items: [{menu_id, quantity}] }
     * Internal form: { guest_count, items: [{ menu_id: package_id, quantity: guest_count,
     *                   is_package: true, modifiers: [{menu_id, quantity}] }] }
     *
     * IMPORTANT: package_id must be resolved to krypton_menu_id for POS integration.
     * The package_id submitted by the tablet can be either:
     *   - The krypton_menu_id (preferred)
     *   - The local packages.id (backward compatibility)
     * We always normalize to krypton_menu_id for the POS ordered_menus insert.
     */
    private function expandIntentPayload(array $data): array
    {
        $packageIdRaw = $data['package_id'] ?? null;
        if (! is_numeric($packageIdRaw) || (int) $packageIdRaw <= 0) {
            throw new InvalidArgumentException('Invalid order payload: package_id is required and must be greater than 0.', 422);
        }

        $guestCountRaw = $data['guest_count'] ?? null;
        if (! is_numeric($guestCountRaw) || (int) $guestCountRaw <= 0) {
            throw new InvalidArgumentException('Invalid order payload: guest_count must be greater than 0.', 422);
        }

        $items = $data['items'] ?? null;
        if (! is_array($items) || $items === []) {
            throw new InvalidArgumentException('Invalid order payload: items must be a non-empty array.', 422);
        }

        $submittedPackageId = (int) $packageIdRaw;
        $guestCount = (int) $guestCountRaw;

        // Resolve submitted package_id to krypton_menu_id
        // First try to find by krypton_menu_id (preferred), then fall back to local id
        $package = Package::query()
            ->where(function ($query) use ($submittedPackageId) {
                $query->where('krypton_menu_id', $submittedPackageId)
                      ->orWhere('id', $submittedPackageId);
            })
            ->where('is_active', true)
            ->first();

        if (! $package) {
            throw new InvalidArgumentException("Invalid order payload: package_id {$submittedPackageId} not found or inactive.", 422);
        }

        $kryptonMenuId = $package->krypton_menu_id;
        if (! $kryptonMenuId || $kryptonMenuId <= 0) {
            throw new InvalidArgumentException("Invalid order payload: package has no valid krypton_menu_id.", 422);
        }

        Log::debug('Package ID resolved', [
            'submitted_package_id' => $submittedPackageId,
            'local_package_id' => $package->id,
            'krypton_menu_id' => $kryptonMenuId,
        ]);

        $modifiers = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("Invalid initial order payload: items.{$index} must be an object.");
            }

            $menuIdRaw = $item['menu_id'] ?? null;
            $quantityRaw = $item['quantity'] ?? null;

            if (! is_numeric($menuIdRaw) || (int) $menuIdRaw <= 0) {
                throw new InvalidArgumentException("Invalid initial order payload: items.{$index}.menu_id must be greater than 0.");
            }

            if (! is_numeric($quantityRaw) || (int) $quantityRaw <= 0) {
                throw new InvalidArgumentException("Invalid initial order payload: items.{$index}.quantity must be greater than 0.");
            }

            $modifiers[] = [
                'menu_id' => (int) $menuIdRaw,
                'quantity' => (int) $quantityRaw,
            ];
        }

        $data['items'] = [
            [
                'menu_id'    => $kryptonMenuId,
                'quantity'   => $guestCount,
                'is_package' => true,
                'modifiers'  => $modifiers,
            ],
        ];

        return $data;
    }

    private function isPosServiceUnavailable(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        if (! str_contains($message, 'connection: pos')) {
            return false;
        }

        $sqlState = strtoupper((string) ($e->errorInfo[0] ?? ''));
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        if ($sqlState === self::POS_SQLSTATE_GENERAL_ERROR && $driverCode === self::POS_CONNECTION_REFUSED_ERROR_CODE) {
            return true;
        }

        return str_contains($message, 'connection refused')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'no such file or directory');
    }

    private function dispatchOrderCreated(DeviceOrder $order, ?int $deviceId): void
    {
        try {
            OrderCreated::dispatch($order);
        } catch (Throwable $e) {
            Log::warning('Order created but realtime broadcast failed', [
                'device_id' => $deviceId,
                'device_order_id' => $order->id,
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
