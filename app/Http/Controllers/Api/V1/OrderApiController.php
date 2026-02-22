<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use Illuminate\Http\Request;
use App\Models\Krypton\TerminalSession;
use App\Models\Krypton\Menu as KryptonMenu;
use App\Actions\Order\CreateOrderedMenu;
use App\Events\PrintRefill;
use App\Events\PrintOrder;
use App\Events\Order\OrderPrinted;
use App\Services\Krypton\KryptonContextService;
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
        $order = DeviceOrder::where(['order_id' => $orderId])->first();
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
     */
    public function refill(Request $request, int $orderId)
    {
        $request->validate([
            'items' => 'required|array',
        ]);

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

        $incomingItems = $request->input('items', []);
        $mappedItems = [];

        foreach ($incomingItems as $i => $it) {
            $name = trim(strval($it['name'] ?? ''));
            $quantity = intval($it['quantity'] ?? 1);

            // Optimization: If both menu_id and price provided, skip DB lookup (testing + API contracts)
            $menu = null;
            if (!empty($it['menu_id']) && isset($it['price'])) {
                $menu = (object) [ 'id' => $it['menu_id'], 'price' => $it['price'] ];
            }
            // Priority 1: Use menu_id to lookup from POS if price not provided
            elseif (!empty($it['menu_id'])) {
                try {
                    $menu = KryptonMenu::find($it['menu_id']);
                } catch (\Throwable $_e) {
                    $menu = null;
                }
            }

            // Priority 2: Fallback to name-based lookup if menu_id lookup failed or not provided
            if (!$menu && !empty($name)) {
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

        try {
            $created = CreateOrderedMenu::run($attrs);

            try {
                PrintRefill::dispatch($deviceOrder, $created);
            } catch (\Throwable $e) {
                report($e);
            }

            // Emit a PrintEvent for the relay device to consume
            try {
                app(\App\Services\PrintEventService::class)->createForOrder($deviceOrder, 'REFILL', [
                    'refill_count' => count($mappedItems),
                    'refilled_at' => now()->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                report($e);
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
