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
    public function index()
    {
        $orders = DeviceOrder::with(['device', 'table'])
            ->activeOrder()
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
