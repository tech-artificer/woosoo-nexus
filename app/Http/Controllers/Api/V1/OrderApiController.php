<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use Illuminate\Http\Request;
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
        //
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
    public function show(DeviceOrder $order)
    {
        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }

    /**
     * Show the specified resource by external order_id field.
     */
    public function showByExternalId(string $orderId)
    {
       
        $order = DeviceOrder::where(['order_id' => $orderId])->first();
        if (! $order) {
            return response()->json([ 'success' => false, 'message' => 'Order not found' ], 404);
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

        $incomingItems = $request->input('items', []);
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

        try {
            $created = CreateOrderedMenu::run($attrs);

            try {
                PrintRefill::dispatch($deviceOrder, $created);
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
