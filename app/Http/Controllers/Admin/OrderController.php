<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Krypton\KryptonContextService;
use Inertia\Inertia;
use App\Repositories\Krypton\OrderRepository;
use App\Repositories\Krypton\TableRepository;
use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
use App\Models\DeviceOrder;
use App\Models\Device;
use App\Models\Krypton\Table as KryptonTable;
use App\Enums\OrderStatus;
use App\Models\Krypton\Session;
use App\Services\Krypton\OrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderCompleted as OrderCompletedEvent;

class OrderController extends Controller
{
    /**
     * Render the admin Orders page with live orders, history, devices, and tables.
     */
    public function index(Request $request)
    {
        $statuses = collect((array) $request->query('status', []))
            ->flatMap(function ($value) {
                return is_string($value) ? explode(',', $value) : [$value];
            })
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $search = trim((string) $request->query('search', ''));
        $deviceId = $request->query('device_id');
        $tableId = $request->query('table_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $orders = DeviceOrder::query()
            ->with(['device', 'table'])
            ->activeOrder()
            ->when($statuses->isNotEmpty(), function ($query) use ($statuses) {
                $query->whereIn('status', $statuses->all());
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('device', function ($deviceQuery) use ($search) {
                            $deviceQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($deviceId, function ($query) use ($deviceId) {
                $query->where('device_id', $deviceId);
            })
            ->when($tableId, function ($query) use ($tableId) {
                $query->where('table_id', $tableId);
            })
            ->when($dateFrom, function ($query) use ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query) use ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
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
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {   
        $deviceOrder = DeviceOrder::find($id); 
      
        $deviceOrder->update(['status' => OrderStatus::VOIDED]);
        $this->orderService->voidOrder($deviceOrder);
         //Run the console command
        // $exitCode = Artisan::call('broadcast:order-voided', [
        //     'order_id' => $deviceOrder->order_id

     
        return redirect()->back()->with('success');
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
                $updated++;
            } catch (\Throwable $e) {
                Log::error('Failed to update order status', ['id' => $id, 'error' => $e->getMessage()]);
                continue;
            }
        }

        return redirect()->back()->with('success', "{$updated} order(s) updated.");
    }

    /**
     * Complete a single order (admin action).
     * Updates status to COMPLETED, observer broadcasts event.
     */
    public function complete(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = DeviceOrder::where('order_id', $validated['order_id'])->firstOrFail();
        
        // Update status → Observer automatically dispatches OrderCompleted event
        $order->update(['status' => OrderStatus::COMPLETED]);

        return redirect()->back()->with('success', 'Order completed successfully');
    }

    /**
     * Mark order as printed and dispatch print event (admin action).
     */
    public function print(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = DeviceOrder::where('order_id', $validated['order_id'])->firstOrFail();
        
        // Update is_printed flag
        $order->update([
            'is_printed' => true,
            'printed_at' => now(),
        ]);

        // Manually dispatch OrderPrinted event for real-time notification
        \App\Events\Order\OrderPrinted::dispatch($order);

        return redirect()->back()->with('success', 'Print job dispatched');
    }
}
