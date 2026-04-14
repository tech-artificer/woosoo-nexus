<?php
// Audit Fix (2026-04-06): restore missing admin order actions and route handlers used by Orders UI.
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\DeviceOrder;
use App\Models\Device;
use App\Models\Krypton\Table as KryptonTable;
use App\Enums\OrderStatus;
use App\Services\Krypton\OrderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderCompleted as OrderCompletedEvent;
use App\Events\PrintOrder;
use Illuminate\Database\Eloquent\Builder;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilter = collect(explode(',', (string) $request->query('status', '')))
            ->map(fn (string $status) => trim($status))
            ->filter()
            ->values();
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $ordersQuery = DeviceOrder::with(['device', 'table'])
            ->activeOrder();

        if ($statusFilter->isNotEmpty()) {
            $ordersQuery->whereIn('status', $statusFilter->all());
        }

        if ($search !== '') {
            $ordersQuery->where(function (Builder $query) use ($search) {
                $query->where('order_number', 'like', '%' . $search . '%')
                    ->orWhere('order_id', 'like', '%' . $search . '%')
                    ->orWhereHas('device', function (Builder $deviceQuery) use ($search) {
                        $deviceQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if (! empty($dateFrom)) {
            $ordersQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if (! empty($dateTo)) {
            $ordersQuery->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $ordersQuery
            ->latest()
            ->get();

        $orderHistory = DeviceOrder::with(['device', 'table'])
            ->completedOrder()
            ->latest()
            ->limit(100)
            ->get();

        $devices = Device::select('id', 'name')->get();
        $tables  = KryptonTable::select('id', 'name')->get();

        return Inertia::render('Orders/Index', [
            'title'        => 'Orders',
            'description'  => 'Manage and monitor live orders',
            'orders'       => $orders,
            'orderHistory' => $orderHistory,
            'devices'      => $devices,
            'tables'       => $tables,
        ]);
    }

    /**
     * API endpoint: Get device order by order_id with all items (including refills).
     */
    public function byOrderId($orderId)
    {
        $deviceOrder = DeviceOrder::with(['items.menu', 'serviceRequests', 'table', 'device'])
            ->where('order_id', $orderId)
            ->first();
        if (! $deviceOrder) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        // Log when items are unexpectedly missing for easier debugging in prod
        try {
            if (! is_countable($deviceOrder->items) || count($deviceOrder->items) === 0) {
                Log::warning('byOrderId: returned order has no items', ['order_id' => $orderId, 'device_order_id' => $deviceOrder->id ?? null]);
            }
        } catch (\Throwable $e) {
            Log::debug('byOrderId: failed to inspect items', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }
        return response()->json($deviceOrder);
    }

    /**
     * Show one order from admin context.
     * Keeps backward compatibility with /orders/{id} route while rendering the existing page.
     */
    public function show(int $id)
    {
        $order = DeviceOrder::with(['items.menu', 'serviceRequests', 'table', 'device', 'printEvents'])->findOrFail($id);

        if (request()->expectsJson() || request()->wantsJson()) {
            return response()->json([
                'order' => $order,
            ]);
        }

        return Inertia::render('Orders/Index', [
            'title' => 'Orders',
            'description' => 'Manage and monitor live orders',
            'orders' => DeviceOrder::with(['device', 'table'])->activeOrder()->latest()->get(),
            'orderHistory' => DeviceOrder::with(['device', 'table'])->completedOrder()->latest()->limit(100)->get(),
            'devices' => Device::select('id', 'name')->get(),
            'tables' => KryptonTable::select('id', 'name')->get(),
            'selectedOrderId' => $order->id,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $deviceOrder = DeviceOrder::findOrFail($id);

        $deviceOrder->update(['status' => OrderStatus::VOIDED]);
        $this->orderService->voidOrder($deviceOrder);

        return redirect()->back()->with('success', 'Order voided successfully.');
    }

    /**
     * Mark one order as completed by external POS order id and broadcast update.
     */
    public function complete(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required'],
        ]);

        $order = DeviceOrder::where('order_id', $validated['order_id'])->first();

        if (! $order) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        try {
            $order->update(['status' => OrderStatus::COMPLETED]);
            event(new OrderStatusUpdated($order));
            event(new OrderCompletedEvent($order));
        } catch (\Throwable $e) {
            Log::error('Failed to complete order', [
                'order_id' => $validated['order_id'],
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to complete order.');
        }

        return redirect()->back()->with('success', 'Order completed successfully.');
    }

    /**
     * Trigger print dispatch for a specific order by external order id.
     */
    public function print(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required'],
        ]);

        $order = DeviceOrder::where('order_id', $validated['order_id'])->first();

        if (! $order) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        PrintOrder::dispatch($order);

        return redirect()->back()->with('success', 'Order sent to printer.');
    }

    /**
     * Bulk complete orders
     */
    public function bulkComplete(Request $request) {
        $validated = $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['required', 'integer'],
        ]);

        $completed = 0;
        foreach ($validated['order_ids'] as $orderId) {
            try {
                Artisan::call('broadcast:order-completed', [
                    'order_id' => $orderId
                ]);
                $completed++;
            } catch (\Exception $e) {
                Log::error("Failed to complete order {$orderId}: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', "{$completed} order(s) completed successfully.");
    }

    /**
     * Bulk void orders
     */
    public function bulkVoid(Request $request) {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:device_orders,id'],
        ]);

        $voided = 0;
        foreach ($validated['ids'] as $id) {
            try {
                $deviceOrder = DeviceOrder::find($id);
                if ($deviceOrder) {
                    $deviceOrder->update(['status' => OrderStatus::VOIDED]);
                    $this->orderService->voidOrder($deviceOrder);
                    $voided++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to void order {$id}: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', "{$voided} order(s) voided successfully.");
    }

    /**
     * Update a single order's status (admin action).
     */
    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', new EnumRule(OrderStatus::class)],
        ]);

        $order = DeviceOrder::findOrFail($id);

        $newStatus = OrderStatus::from($validated['status']);

        try {
            $order->update(['status' => $newStatus]);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid status transition attempted', ['order' => $order->id, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Invalid status transition.');
        }

        // Broadcast status update
        event(new OrderStatusUpdated($order));

        // If completed, also dispatch the completed event
        if ($newStatus === OrderStatus::COMPLETED) {
            event(new OrderCompletedEvent($order));
        }

        return redirect()->back()->with('success', true);
    }

    /**
     * Bulk update order statuses (admin action).
     */
    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:device_orders,id'],
            'status' => ['required', new EnumRule(OrderStatus::class)],
        ]);

        $status = OrderStatus::from($validated['status']);
        $updated = 0;

        foreach ($validated['ids'] as $id) {
            try {
                $deviceOrder = DeviceOrder::find($id);
                if (! $deviceOrder) continue;
                $deviceOrder->update(['status' => $status]);
                event(new OrderStatusUpdated($deviceOrder));
                if ($status === OrderStatus::COMPLETED) {
                    event(new OrderCompletedEvent($deviceOrder));
                }
                $updated++;
            } catch (\Throwable $e) {
                Log::error('Failed to update order status', ['id' => $id, 'error' => $e->getMessage()]);
                continue;
            }
        }

        return redirect()->back()->with('success', "{$updated} order(s) updated.");
    }
}
