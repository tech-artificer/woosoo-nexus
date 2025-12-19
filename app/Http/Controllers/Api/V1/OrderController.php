<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeviceOrder;
use Illuminate\Validation\Rules\Enum as EnumRule;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Device-only index: filters, pagination, and minimal payloads.
     */
    public function index(Request $request)
    {
        $device = $request->user();

        $query = DeviceOrder::with(['items'])->orderBy('created_at', 'desc');

        // Restrict to device's branch if available
        if ($device && isset($device->branch_id)) {
            $query->where('branch_id', $device->branch_id);
        }

        // Status filter (comma separated)
        if ($statuses = $request->query('status')) {
            $arr = array_filter(array_map('trim', explode(',', $statuses)));
            if (! empty($arr)) {
                $query->whereIn('status', $arr);
            }
        }

        // Item status filter
        if ($itemStatuses = $request->query('item_status')) {
            $arr = array_filter(array_map('trim', explode(',', $itemStatuses)));
            if (! empty($arr)) {
                $query->whereHas('items', function ($q) use ($arr) {
                    $q->whereIn('status', $arr);
                });
            }
        }

        if ($sessionId = $request->query('session_id')) {
            $query->where('session_id', $sessionId);
        }

        // Branch filter (device or explicit query)
        if ($branch = $request->query('branch')) {
            $query->where('branch_id', $branch);
        }

        // Station filter: map to table_id by convention
        if ($station = $request->query('station')) {
            $query->where('table_id', $station);
        }

        if ($since = $request->query('since')) {
            $query->where('created_at', '>=', $since);
        }
        if ($until = $request->query('until')) {
            $query->where('created_at', '<=', $until);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('order_id', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 25);
        $paginated = $query->paginate($perPage)->appends($request->query());

        // Compute simple status counts scoped to device/branch
        $countsQuery = DeviceOrder::query();
        if ($device && isset($device->branch_id)) {
            $countsQuery->where('branch_id', $device->branch_id);
        }
        if ($branch) {
            $countsQuery->where('branch_id', $branch);
        }
        if ($station) {
            $countsQuery->where('table_id', $station);
        }
        $counts = $countsQuery->select('status', DB::raw('count(*) as cnt'))->groupBy('status')->pluck('cnt', 'status')->toArray();

        $statusKeys = ['pending', 'in_progress', 'ready', 'completed', 'cancelled'];
        $statusCounts = [];
        foreach ($statusKeys as $k) {
            $statusCounts[$k] = isset($counts[$k]) ? (int) $counts[$k] : 0;
        }

        $payload = $paginated->getCollection()->map(function ($order) {
            return [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn($it) => [
                    'id' => $it->id,
                    'menu_id' => $it->menu_id,
                    'quantity' => $it->quantity,
                    'price' => $it->price,
                    'status' => $it->status ?? 'pending',
                ])->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $payload,
            'meta' => [
                'total' => $paginated->total(),
                'page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'counts' => $statusCounts,
            ],
        ]);
    }

    /**
     * Show a single order (device-visible minimal fields).
     */
    public function show(Request $request, $orderId)
    {
        $device = $request->user();
        $order = DeviceOrder::with('items')->where('order_id', $orderId)->firstOrFail();

        if ($device && isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn($it) => [
                    'id' => $it->id,
                    'menu_id' => $it->menu_id,
                    'quantity' => $it->quantity,
                    'price' => $it->price,
                    'status' => $it->status ?? 'pending',
                ])->values(),
            ],
        ]);
    }

    /**
     * Update a single order's status (server-enforced transitions).
     */
    public function updateStatus(Request $request, DeviceOrder $order)
    {
        $request->validate([
            'status' => ['required', new EnumRule(OrderStatus::class)],
        ]);

        $device = $request->user();

        if ($device && isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            $order->status = OrderStatus::from($request->input('status'));
            $order->save();
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Invalid status transition', 'error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => [ 'id' => $order->id, 'status' => $order->status ]]);
    }

    /**
     * Bulk update statuses for multiple orders.
     */
    public function bulkStatus(Request $request)
    {
        $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['required'],
            'status' => ['required', new EnumRule(OrderStatus::class)],
        ]);

        $device = $request->user();
        $orderIds = $request->input('order_ids', []);
        $targetStatus = OrderStatus::from($request->input('status'));

        $results = ['updated' => [], 'failed' => []];

        DB::beginTransaction();
        try {
            foreach ($orderIds as $oid) {
                // Lookup by DeviceOrder ID (internal ID)
                $order = DeviceOrder::find($oid);
                if (! $order) {
                    $results['failed'][] = ['order_id' => $oid, 'reason' => 'not_found'];
                    continue;
                }

                if ($device && isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
                    $results['failed'][] = ['order_id' => $oid, 'reason' => 'forbidden'];
                    continue;
                }

                try {
                    $order->status = $targetStatus;
                    $order->save();
                    $results['updated'][] = $order->order_id;
                } catch (\Throwable $e) {
                    $results['failed'][] = ['order_id' => $oid, 'reason' => 'invalid_transition', 'message' => $e->getMessage()];
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Bulk update failed', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'results' => $results]);
    }
}
