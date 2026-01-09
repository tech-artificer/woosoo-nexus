<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefillOrderRequest;
use App\Models\DeviceOrder;
use Illuminate\Http\Request;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\Menu as KryptonMenu;
use App\Actions\Order\CreateOrderedMenu;
use App\Events\PrintRefill;
use App\Events\PrintOrder;
use App\Events\Order\OrderPrinted;
use App\Services\Krypton\KryptonContextService;
use App\Services\PrintEventService;
use Illuminate\Support\Facades\DB;

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
            if (!empty($statusArr)) {
                $query->whereIn('status', $statusArr);
            }
        }

        if ($sessionId = $request->query('session_id')) {
            $query->where('session_id', $sessionId);
        }

        // If authenticated as a device, restrict to device's branch
        $device = $request->user();
        if ($device && isset($device->branch_id)) {
            $query->where('branch_id', $device->branch_id);
        }

        $perPage = (int) $request->query('per_page', 25);

        $paginated = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\DeviceOrderResource::collection($paginated)->response()->getData(true),
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
            'order' => $order
        ]);
    }

    /**
     * Show the specified resource by external order_id field.
     */
    public function showByExternalId(Request $request, string $orderId)
    {
        $order = DeviceOrder::with(['items.menu', 'table', 'device'])->where(['order_id' => $orderId])->first();
        if (! $order) {
            return response()->json([ 'success' => false, 'message' => 'Order not found' ], 404);
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
            'order' => $order,
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

            // If caller provided a `menu_id` and `price`, prefer that (no POS lookup required).
            $menu = null;
            if (!empty($it['menu_id']) && isset($it['price'])) {
                $menu = (object) [ 'id' => $it['menu_id'], 'price' => $it['price'] ];
            } else {
                try {
                    $menu = KryptonMenu::whereRaw('LOWER(receipt_name) = ?', [strtolower($name)])->first()
                        ?? KryptonMenu::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                } catch (\Throwable $_e) {
                    $menu = null;
                }
            }

            if (! $menu) {
                return response()->json(['success' => false, 'message' => "Menu item not found: {$name}"], 422);
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

        $kctx = new KryptonContextService();
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
            if (is_array($it)) return (object) $it;
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
            $menuNames = !empty($menuIds)
                ? \App\Models\Krypton\Menu::whereIn('id', $menuIds)->pluck('receipt_name', 'id')
                : collect();
            
            $metaItems = collect($posItems)->map(function($item) use ($menuNames) {
                $menuId = $item->menu_id;
                $menuName = $item->name ?? $item->receipt_name ?? $menuNames->get($menuId);
                
                return [
                    'menu_id' => $menuId,
                    'quantity' => $item->quantity ?? 1,
                    'name' => $menuName ?? "Menu #{$menuId}",
                ];
            })->values()->all();

            // Build local payload for each POS item.
            // Each payload mirrors a POS ordered_menu into local device_order_items.
            $localPayloads = [];
            foreach ($posItems as $pos) {
                $menuId = $pos->menu_id;
                $quantity = $pos->quantity ?? 1;
                $price = $pos->price ?? 0.00;
                $subtotal = $pos->sub_total ?? ($pos->subtotal ?? ($price * $quantity));
                $tax = $pos->tax ?? 0.00;
                $total = $pos->total ?? $subtotal;

                $localPayloads[] = [
                    'order_id' => $deviceOrder->id,
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
                ];
            }

            // Mirror POS rows into local device_order_items with retry logic.
            // POS-first contract: if POS succeeded, local mirror MUST succeed (with retries).
            // After local success, dispatch print/broadcast events.
            $maxAttempts = 3;
            $attempt = 0;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    DB::transaction(function () use ($deviceOrder, $posItems, $metaItems, $localPayloads) {
                        // Batch insert all payloads in one query.
                        if (!empty($localPayloads)) {
                            \App\Models\DeviceOrderItems::query()->insert($localPayloads);
                        }

                        // After successful local transaction, schedule print/broadcast.
                        DB::afterCommit(function () use ($deviceOrder, $metaItems, $posItems) {
                            try {
                                app(PrintEventService::class)->createForOrder(
                                    $deviceOrder,
                                    'REFILL',
                                    ['items' => $metaItems]
                                );
                            } catch (\Throwable $e) {
                                report($e);
                            }

                            try {
                                PrintRefill::dispatch($deviceOrder, $posItems);
                            } catch (\Throwable $e) {
                                report($e);
                            }
                        });
                    });

                    // Success — break retry loop
                    break;
                } catch (\Throwable $e) {
                    if ($attempt >= $maxAttempts) {
                        \Illuminate\Support\Facades\Log::error('Refill local mirror failed after max retries', [
                            'order_id' => $orderId,
                            'device_order_id' => $deviceOrder->id,
                            'attempt' => $attempt,
                            'error' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                    // Log retry attempt (no sleep; immediate retry)
                    \Illuminate\Support\Facades\Log::warning('Refill local mirror retry', [
                        'order_id' => $orderId,
                        'device_order_id' => $deviceOrder->id,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json(['success' => true, 'created' => $created]);
        } catch (\Throwable $th) {
            report($th);
            return response()->json(['success' => false, 'message' => 'Failed to persist refill', 'error' => $th->getMessage()], 500);
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
            'items' => $items->map(fn($it) => [
                'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? null,
                'quantity' => $it->quantity ?? null,
            ])->values()->all(),
        ]);
    }
}
