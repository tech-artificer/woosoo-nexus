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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderCompleted as OrderCompletedEvent;

class OrderController extends Controller
{
    
    protected $orderService;

    public function __construct(OrderService $orderService) {
        $this->orderService = $orderService;
    }
    /**
     * Render the orders page.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $tableRepo = new TableRepository();
        $activeOrders = $tableRepo->getActiveTableOrders();

        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            $session = Session::orderByDesc('id')->first();
        } else {
            $session = Session::fromQuery('CALL get_latest_session_id()')->first();
        }

        // Get session_id from POS or use query parameter for testing
        // No fallback to hardcoded 1; if no session available, this is a data integrity issue
        $sessionId = $session?->id ?? $request->query('session_id');
        
        if (!$sessionId) {
            // Return empty orders if no session is available (safer than showing random data)
            return Inertia::render('Orders/IndexOrders', [
                'orders' => [],
                'stats' => ['totalOrders' => 0, 'liveOrders' => 0, 'completedOrders' => 0],
                'activeOrders' => $activeOrders,
            ]);
        }

        // Apply optional query filters (status, device, table, search, date range)
        $filters = $request->only(['status', 'device_id', 'table_id', 'search', 'date_from', 'date_to']);

        $ordersQuery = DeviceOrder::with(['device', 'order', 'table', 'serviceRequests'])
                ->where('session_id', $sessionId)
                ->activeOrder();

        // status may be comma-separated or array
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : explode(',', $filters['status']);
            $ordersQuery->whereIn('status', array_map(fn($s) => trim($s), $statuses));
        }

        if (!empty($filters['device_id'])) {
            $ordersQuery->where('device_id', $filters['device_id']);
        }

        if (!empty($filters['table_id'])) {
            $ordersQuery->where('table_id', $filters['table_id']);
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $ordersQuery->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhereHas('device', fn($q2) => $q2->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('table', fn($q2) => $q2->where('name', 'like', "%{$s}%"));
            });
        }

        if (!empty($filters['date_from'])) {
            $ordersQuery->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $ordersQuery->where('created_at', '<=', $filters['date_to']);
        }

        $orders = $ordersQuery->orderBy('table_id', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Order history (completed/terminal) — apply same filters where reasonable
        $historyQuery = DeviceOrder::with(['device', 'order', 'table', 'serviceRequests'])
                ->where('session_id', $sessionId)
                ->completedOrder();

        if (!empty($filters['status'])) {
            $historyQuery->whereIn('status', array_map(fn($s) => trim($s), $statuses ?? (is_array($filters['status']) ? $filters['status'] : explode(',', $filters['status']))));
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $historyQuery->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhereHas('device', fn($q2) => $q2->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('table', fn($q2) => $q2->where('name', 'like', "%{$s}%"));
            });
        }

        $orderHistory = $historyQuery->orderBy('updated_at', 'desc')->get();
        
        // simple stats and sparkline for orders
        $today = \Carbon\Carbon::today();
        $start = $today->copy()->subDays(6)->startOfDay();

        $daily = DeviceOrder::where('created_at', '>=', $start)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as cnt")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('cnt', 'date')
            ->toArray();

        $spark = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $spark[] = isset($daily[$d]) ? (int) $daily[$d] : 0;
        }

        $stats = [
            [ 'title' => 'Live Orders', 'value' => $orders->count(), 'subtitle' => 'Pending & in-progress', 'variant' => 'primary', 'sparkline' => $spark ],
            [ 'title' => 'Order History', 'value' => $orderHistory->count(), 'subtitle' => 'Completed / Voided', 'variant' => 'default' ],
        ];

        // Provide helper lists for the UI filters
        try {
            $devices = Device::orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            $devices = collect([]);
        }

        try {
            $tables = KryptonTable::orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            $tables = collect([]);
        }

        return Inertia::render('Orders/Index', [
            'title' => 'Orders',
            'description' => 'Daily Orders',    
            'orders' => $orders,
            'orderHistory' => $orderHistory,
            'stats' => $stats,
            'filters' => $filters,
            'devices' => $devices,
            'tables' => $tables,
            // 'user' => auth()->user(),
            // 'tableOrders' => $activeOrders,
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
    public function show(int $id)
    {
        $order = DeviceOrder::with([
            'device',
            'table',
            'order.orderCheck',
            'items' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
            'items.menu',
            'serviceRequests',
            'printEvents' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
        ])->findOrFail($id);

        Gate::authorize('view', $order);

        return response()->json([
            'order' => new \App\Http\Resources\DeviceOrderResource($order),
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
    public function update(Request $request, Order $order)
    {
        return back()->with(['success' => true]);
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
        // ]);

        // Optional: capture output
        // $output = Artisan::output();

        return redirect()->back()->with('success');
    }

    public function complete(Request $request) {

        $orderId = $request->input('order_id');

        //Run the console command
        $exitCode = Artisan::call('broadcast:order-completed', [
            'order_id' => $orderId
        ]);

        // Optional: capture output
        $output = Artisan::output();

     
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
