<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Order\CreateOrderedMenu;
use App\Events\Order\OrderPrinted;
use App\Events\PrintOrder;
use App\Events\PrintRefill;
use App\Http\Controllers\Controller;
use App\Http\Requests\RefillOrderRequest;
use App\Http\Resources\DeviceOrderResource;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\Krypton\Menu;
use App\Models\Krypton\Menu as KryptonMenu;
use App\Services\Krypton\KryptonContextService;
use App\Services\PrintEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderApiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Support basic filtering and pagination for device/admin UIs.
        $request = request();

        $query = DeviceOrder::with(['device', 'table', 'items.menu'])->orderBy('created_at', 'desc');

        // Optional status filter: comma-separated list of status values
        if ($statuses = $request->query('status')) {
            $statusArr = array_filter(array_map('trim', explode(',', $statuses)));
            if (! empty($statusArr)) {
                $query->whereIn('status', $statusArr);
            }
        }

        if ($sessionId = $request->query('session_id')) {
            $query->where('session_id', $sessionId);
        }

        // If authenticated as a device, restrict to that specific device.
        // Branch scoping alone can leak sibling tablet orders and break
        // device-local active-order recovery in the PWA.
        $device = $request->user();
        if ($device && isset($device->id)) {
            $query->where('device_id', $device->id);
        }

        if ($device && isset($device->branch_id)) {
            $query->where('branch_id', $device->branch_id);
        }

        $perPage = (int) $request->query('per_page', 25);

        $paginated = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => DeviceOrderResource::collection($paginated)->response()->getData(true),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, DeviceOrder $order)
    {
        $device = $request->user();

        // Branch-level authorization: devices may only access orders for their branch
        if ($device && isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Optional session scoping: if client supplied a session_id, ensure it matches the order
        $sessionId = $request->input('session_id');
        if ($sessionId && $order->session_id != $sessionId) {
            return response()->json(['success' => false, 'message' => 'Session mismatch'], 403);
        }

        // Eager-load relationships to prevent N+1 queries
        $order->loadMissing(['items.menu', 'table', 'device']);

        return response()->json([
            'success' => true,
            'order' => new DeviceOrderResource($order),
        ]);
    }

    /**
     * Show the specified resource by external order_id field.
     */
    public function showByExternalId(Request $request, string $orderId)
    {
        $order = DeviceOrder::with(['items.menu', 'table', 'device'])->where(['order_id' => $orderId])->first();
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $device = $request->user();
        if ($device && isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $sessionId = $request->input('session_id');
        if ($sessionId && $order->session_id != $sessionId) {
            return response()->json(['success' => false, 'message' => 'Session mismatch'], 403);
        }

        return response()->json([
            'success' => true,
            'order' => new DeviceOrderResource($order),
        ]);
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

    /**
     * Persist refill items and dispatch print event.
     *
     * Validates that items are refillable (meats/sides only).
     */
    public function refill(RefillOrderRequest $request, int $orderId)
    {
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key', ''));
        $idempotencyScope = null;
        $processingKey = null;
        $responseCacheKey = null;

        if ($idempotencyKey !== '') {
            $requestDevice = $request->user();
            $requestDeviceId = $requestDevice && isset($requestDevice->id) ? (string) $requestDevice->id : 'anonymous';
            $idempotencyScope = 'refill:'.$requestDeviceId.':'.$orderId.':'.sha1($idempotencyKey);
            $processingKey = $idempotencyScope.':processing';
            $responseCacheKey = $idempotencyScope.':response';

            $cachedResponse = Cache::get($responseCacheKey);
            if (is_array($cachedResponse)) {
                return response()->json(
                    $cachedResponse['body'] ?? ['success' => true, 'message' => 'Refill request replayed'],
                    (int) ($cachedResponse['status'] ?? 200),
                    ['X-Idempotent-Replay' => 'true']
                );
            }

            // Prevent duplicate in-flight refill requests with the same idempotency key.
            if (! Cache::add($processingKey, 1, now()->addSeconds(30))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate refill request already processing',
                ], 409);
            }
        }

        Log::info('[REFILL] Received refill request', [
            'order_id' => $orderId,
            'ip' => $request->ip(),
            'items_count' => count($request->input('items', [])),
        ]);

        // RefillOrderRequest automatically validates items
        $validatedData = $request->validated();

        $deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
        if (! $deviceOrder) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Authorization: ensure device (if any) is allowed to operate on this order
        $device = $request->user();
        if ($device && isset($device->branch_id) && isset($deviceOrder->branch_id) && $device->branch_id !== $deviceOrder->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // Session scoping: if client provided session_id, ensure it matches
        $sessionId = $request->input('session_id');
        if ($sessionId && $deviceOrder->session_id != $sessionId) {
            return response()->json(['success' => false, 'message' => 'Session mismatch'], 403);
        }

        $incomingItems = $validatedData['items'] ?? [];
        $mappedItems = [];

        foreach ($incomingItems as $i => $it) {
            $name = trim(strval($it['name'] ?? ''));
            $quantity = intval($it['quantity'] ?? 1);

            // Optimization: If both menu_id and price provided, skip DB lookup (testing + API contracts)
            $menu = null;
            if (! empty($it['menu_id']) && isset($it['price'])) {
                $menu = (object) ['id' => $it['menu_id'], 'price' => $it['price']];
            }
            // Priority 1: Use menu_id to lookup from POS if price not provided
            elseif (! empty($it['menu_id'])) {
                try {
                    $menu = KryptonMenu::find($it['menu_id']);
                } catch (\Throwable $_e) {
                    $menu = null;
                }
            }

            // Priority 2: Fallback to name-based lookup if menu_id lookup failed or not provided
            if (! $menu && ! empty($name)) {
                try {
                    $menu = KryptonMenu::whereRaw('LOWER(receipt_name) = ?', [strtolower($name)])->first()
                        ?? KryptonMenu::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                } catch (\Throwable $_e) {
                    $menu = null;
                }
            }

            if (! $menu) {
                $menuRef = $it['menu_id'] ?? $name ?? 'unknown';

                return response()->json(['success' => false, 'message' => "Menu item not found: {$menuRef}"], 422);
            }

            $mappedItems[] = [
                'menu_id' => $menu->id,
                'quantity' => $quantity,
                'index' => $it['index'] ?? ($i + 1),
                'seat_number' => $it['seat_number'] ?? 1,
                'note' => $it['note'] ?? 'Refill',
                'price' => $menu->price,
            ];
        }

        $kctx = app(KryptonContextService::class);
        $kdata = $kctx->getData();

        $attrs = [
            'order_id' => $orderId,
            'order_check_id' => $deviceOrder->order_check_id ?? null,
            // employee_log_id originates from Krypton (POS), not the local app user
            'employee_log_id' => $kdata['employee_log_id'] ?? null,
            'device_order_id' => $deviceOrder->id,
            'items' => $mappedItems,
        ];

        // Run POS-side inserts. Controller handles local mirroring and retries.
        $attrs['mirror_local'] = false;
        $created = CreateOrderedMenu::run($attrs);

        // Normalize created items: POS returns raw ordered_menu records.
        // Extract them into objects for consistent handling.
        $posItems = collect($created)->map(function ($it) {
            if (is_array($it)) {
                return (object) $it;
            }

            return $it;
        })->values()->all();

        // Guard: if no items created in POS, nothing to mirror locally
        if (empty($posItems)) {
            return response()->json(['success' => true, 'created' => []]);
        }

        try {
            // Build metadata for broadcast (menu names, quantities).
            // Batch-load menu names to prevent N+1 queries.
            $menuIds = collect($posItems)->pluck('menu_id')->filter()->unique()->values()->all();
            Log::info('Refill menu IDs extracted', ['menu_ids' => $menuIds]);

            $menuNames = ! empty($menuIds)
                ? Menu::whereIn('id', $menuIds)->pluck('receipt_name', 'id')
                : collect();

            Log::info('Refill menu names loaded', ['menu_names' => $menuNames->all()]);

            $metaItems = collect($posItems)->map(function ($item) use ($menuNames) {
                $menuId = $item->menu_id;
                $menuName = $item->name ?? $item->receipt_name ?? $menuNames->get($menuId);

                Log::info('Refill item mapping', [
                    'menu_id' => $menuId,
                    'item_name' => $item->name ?? null,
                    'item_receipt_name' => $item->receipt_name ?? null,
                    'lookup_name' => $menuNames->get($menuId),
                    'final_name' => $menuName ?? "Menu #{$menuId}",
                ]);

                return [
                    'menu_id' => $menuId,
                    'quantity' => $item->quantity ?? 1,
                    'name' => $menuName ?? "Menu #{$menuId}",
                ];
            })->values()->all();

            Log::info('Refill metaItems constructed', ['meta_items' => $metaItems]);

            $refillEventMeta = [
                'items' => $metaItems,
                'refill_count' => count($mappedItems),
                'refilled_at' => now()->toIso8601String(),
            ];

            // Build local payload for each POS item.
            // Each payload mirrors a POS ordered_menu into local device_order_items.
            $localPayloads = [];
            foreach ($posItems as $pos) {
                $menuId = $pos->menu_id;
                $quantity = $pos->quantity ?? 1;
                $price = $pos->price ?? ($pos->unit_price ?? 0.00);
                $subtotal = $pos->sub_total ?? ($pos->subtotal ?? ($price * $quantity));
                $tax = $pos->tax ?? 0.00;
                $total = $pos->total ?? $subtotal;

                $localPayloads[] = [
                    'order_id' => $deviceOrder->id,      // FK to device_orders.id
                    'ordered_menu_id' => $pos->id,  // POS ordered_menu record ID
                    'menu_id' => $menuId,            // FK to menus table
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'notes' => $pos->note ?? null,
                    'seat_number' => $pos->seat_number ?? 1,
                    'index' => $pos->index ?? 1,
                    // Note: is_refill column removed - table doesn't have it
                ];
            }

            // Mirror POS rows into local device_order_items with retry logic.
            // POS-first contract: if POS succeeded, local mirror MUST succeed (with retries).
            // After local success, dispatch print/broadcast events.
            $maxAttempts = 3;
            $attempt = 0;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    DB::transaction(function () use ($deviceOrder, $posItems, $localPayloads, $refillEventMeta) {
                        // Batch insert all payloads in one query.
                        if (! empty($localPayloads)) {
                            DeviceOrderItems::query()->insert($localPayloads);
                        }

                        // DB::afterCommit fires once the outer transaction commits.
                        // It is intentionally nested inside the retry loop: if the outer
                        // transaction is retried, this callback is re-registered each time,
                        // so it only runs on the attempt that actually commits. Do NOT hoist
                        // it outside the loop — that would fire on every iteration, not just
                        // the successful one.
                        DB::afterCommit(function () use ($deviceOrder, $refillEventMeta, $posItems) {
                            try {
                                // Create exactly one refill print event after the local mirror commits.
                                DB::transaction(function () use ($deviceOrder, $refillEventMeta, $posItems) {
                                    app(PrintEventService::class)->createForOrder(
                                        $deviceOrder,
                                        'REFILL',
                                        $refillEventMeta
                                    );
                                    // Reload to pick up printEvent relation
                                    $deviceOrder->refresh();
                                    PrintRefill::dispatch($deviceOrder, $posItems);
                                });
                            } catch (\Throwable $e) {
                                report($e);
                            }
                        });
                    });

                    // Success — break retry loop
                    break;
                } catch (\Throwable $e) {
                    if ($attempt >= $maxAttempts) {
                        Log::error('Refill local mirror failed after max retries', [
                            'order_id' => $orderId,
                            'device_order_id' => $deviceOrder->id,
                            'attempt' => $attempt,
                            'error' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                    // Log retry attempt (no sleep; immediate retry)
                    Log::warning('Refill local mirror retry', [
                        'order_id' => $orderId,
                        'device_order_id' => $deviceOrder->id,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $responseBody = ['success' => true, 'created' => $created];

            if ($responseCacheKey) {
                Cache::put($responseCacheKey, [
                    'status' => 200,
                    'body' => $responseBody,
                ], now()->addMinutes(10));
            }

            return response()->json($responseBody);
        } catch (\Throwable $th) {
            report($th);

            return response()->json(['success' => false, 'message' => 'Failed to persist refill', 'error' => $th->getMessage()], 500);
        } finally {
            if ($processingKey) {
                Cache::forget($processingKey);
            }
        }
    }

    /**
     * Mark a device order as printed.
     */
    public function markPrinted(Request $request, int $orderId)
    {
        $deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
        if (! $deviceOrder) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $device = $request->user();
        if ($device && isset($device->branch_id) && isset($deviceOrder->branch_id) && $device->branch_id !== $deviceOrder->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $sessionId = $request->input('session_id');
        if ($sessionId && $deviceOrder->session_id != $sessionId) {
            return response()->json(['success' => false, 'message' => 'Session mismatch'], 403);
        }

        $deviceOrder->is_printed = true;
        $deviceOrder->printed_at = now();
        $deviceOrder->save();

        try {
            PrintOrder::dispatch($deviceOrder);
            OrderPrinted::dispatch($deviceOrder);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Dispatch a print job for the specified order.
     */
    public function dispatch(int $orderId)
    {
        $order = DeviceOrder::where('order_id', $orderId)->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // Branch/session checks are not strictly enforced for this internal dispatch endpoint.
        PrintOrder::dispatch($order);

        return response()->json(['success' => true]);
    }

    /**
     * Get print-ready order data for the specified order.
     */
    public function print(int $orderId)
    {
        $order = DeviceOrder::where('order_id', $orderId)->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $items = $order->items()->with('menu')->orderBy('index')->get();

        return response()->json([
            'order' => $order->only([
                'id',
                'order_id',
                'order_number',
                'device_id',
                'status',
                'created_at',
                'guest_count',
            ]),
            'tablename' => $order->table->name ?? null,
            'items' => $items->map(fn ($it) => [
                'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? null,
                'quantity' => $it->quantity ?? null,
            ])->values()->all(),
        ]);
    }
}
