<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\Order\OrderPrinted;
use App\Events\PrintOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\AckPrintEventRequest;
use App\Http\Requests\FailPrintEventRequest;
use App\Http\Requests\GetUnprintedOrdersRequest;
use App\Http\Requests\MarkOrderPrintedBulkRequest;
use App\Http\Requests\MarkOrderPrintedRequest;
use App\Http\Requests\PrinterHeartbeatRequest;
use App\Models\DeviceOrder;
// TerminalSession checks removed; sessions are device-local
use App\Models\PrintEvent;
use App\Services\PrintEventService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PrinterApiController extends Controller
{
    protected PrintEventService $printEventService;

    public function __construct(PrintEventService $printEventService)
    {
        $this->printEventService = $printEventService;
    }

    public function markPrinted(MarkOrderPrintedRequest $request, int $orderId)
    {
        $deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
        if (! $deviceOrder) {
            return response()->json(['success' => false, 'message' => 'Order not found', 'data' => null], 404);
        }

        /** @var \App\Models\DeviceOrder $deviceOrder */

        // Ensure the authenticated device is allowed to operate on this order (branch-level check)
        $device = Auth::user();
        if ($device && isset($deviceOrder->branch_id) && isset($device->branch_id) && $device->branch_id !== $deviceOrder->branch_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($deviceOrder->is_printed) {
            return response()->json([
                'success' => true,
                'message' => 'Order was already printed',
                'data' => [
                    'order_id' => $deviceOrder->order_id,
                    'is_printed' => $deviceOrder->is_printed,
                    'printed_at' => $deviceOrder->printed_at,
                ],
            ]);
        }

        $deviceOrder->is_printed = true;
        $printedAtInput = $request->input('printed_at');
        $deviceOrder->printed_at = $printedAtInput ? Carbon::parse($printedAtInput)->utc() : Carbon::now()->utc();
        $deviceOrder->printed_by = $request->input('printer_id');
        $deviceOrder->save();

        try {
            PrintOrder::dispatch($deviceOrder);
            OrderPrinted::dispatch($deviceOrder);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order marked as printed',
            'data' => [
                'order_id' => $deviceOrder->order_id,
                'is_printed' => $deviceOrder->is_printed,
                'printed_at' => $deviceOrder->printed_at,
            ],
        ]);
    }

    public function getUnprintedOrders(GetUnprintedOrdersRequest $request)
    {
        $sessionId = $request->input('session_id');
        $since = $request->input('since');
        $limit = min((int) $request->input('limit', 100), 200);

        // If no session_id provided, fetch the latest open session from Krypton POS
        if (! $sessionId) {
            try {
                $result = DB::connection('pos')->select('CALL get_latest_session_id()');
                if (! empty($result)) {
                    $sessionId = $result[0]->session_id ?? $result[0]->id ?? null;
                    Log::info('Auto-fetched latest session from Krypton', ['session_id' => $sessionId]);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to fetch latest session from Krypton', ['error' => $e->getMessage()]);
            }
        }

        $ordersQuery = DeviceOrder::where('session_id', $sessionId)
            ->where('is_printed', 0)
            ->whereNotIn('status', ['CANCELLED', 'VOIDED'])
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->with(['items', 'table', 'device', 'order'])
            ->orderBy('created_at', 'asc');

        // Enforce branch restriction: only return orders for the authenticated device's branch
        $device = Auth::user();
        if ($device && isset($device->branch_id)) {
            $ordersQuery->where('branch_id', $device->branch_id);
        }

        $orders = $ordersQuery->limit($limit)->get();

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'order_number' => $order->order_number,
                'session_id' => $order->session_id,
                'tablename' => $order->table?->name ?? 'Unknown Table',
                'guest_count' => $order->guest_count,
                'status' => $order->status,
                'is_printed' => $order->is_printed,
                'created_at' => $order->created_at instanceof \DateTimeInterface ? $order->created_at->format(DATE_ATOM) : null,
                'order' => [
                    'order_id' => $order->order_id,
                    'order_number' => $order->order_number,
                    'guest_count' => $order->guest_count,
                    'created_at' => $order->created_at instanceof \DateTimeInterface ? $order->created_at->format(DATE_ATOM) : null,
                ],
                'items' => $order->items?->map(fn ($item) => [
                    'id' => $item->id,
                    'menu_id' => $item->menu_id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'note' => $item->notes ?? null,
                ])->values() ?? [],
            ];
        });

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'count' => $formattedOrders->count(),
            'orders' => $formattedOrders,
        ]);
    }

    public function markPrintedBulk(MarkOrderPrintedBulkRequest $request)
    {
        $orderIds = $request->input('order_ids');
        $printedAtInput = $request->input('printed_at');
        $printedAt = $printedAtInput ? Carbon::parse($printedAtInput)->utc() : Carbon::now()->utc();
        $printerId = $request->input('printer_id');

        $updated = [];
        $alreadyPrinted = [];
        $notFound = [];

        foreach ($orderIds as $orderId) {
            $order = DeviceOrder::where('order_id', $orderId)->first();
            if (! $order) {
                $notFound[] = $orderId;

                continue;
            }

            if ($order->is_printed) {
                $alreadyPrinted[] = $orderId;

                continue;
            }

            $order->update([
                'is_printed' => 1,
                'printed_at' => $printedAt,
                'printed_by' => $printerId,
            ]);

            try {
                PrintOrder::dispatch($order);
                OrderPrinted::dispatch($order);
            } catch (\Throwable $e) {
                report($e);
            }

            $updated[] = $orderId;
        }

        return response()->json([
            'success' => true,
            'message' => count($updated).' orders marked as printed',
            'data' => [
                'updated' => $updated,
                'already_printed' => $alreadyPrinted,
                'not_found' => $notFound,
            ],
        ]);
    }

    /**
     * Return unacknowledged PrintEvents for printers to consume.
     */
    public function getUnprintedEvents(GetUnprintedOrdersRequest $request)
    {
        $limitInput = (int) $request->input('limit', 100);
        $limit = max(1, min($limitInput, 200));
        $since = $request->input('since');

        // Optional auth for relay device emergency mode
        $device = Auth::guard('device')->user();

        Log::info('Printer polling for events', [
            'device_id' => $device?->id,
            'device_name' => $device?->name,
            'since' => $since,
            'session_id' => $request->input('session_id'),
            'limit' => $limit,
        ]);

        $eventsQuery = PrintEvent::where('is_acknowledged', false)
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->with(['deviceOrder', 'deviceOrder.items.menu', 'deviceOrder.table'])
            ->orderBy('created_at', 'asc');

        // Restrict to events for the same branch as the authenticated device
        if ($device && isset($device->branch_id)) {
            $eventsQuery->whereHas('deviceOrder', fn ($q) => $q->where('branch_id', $device->branch_id));
        }

        // Optionally restrict by session if provided
        if ($request->filled('session_id')) {
            $eventsQuery->whereHas('deviceOrder', fn ($q) => $q->where('session_id', $request->input('session_id')));
        }

        $events = $eventsQuery->limit($limit)->get();

        Log::info('Returning print events to printer', [
            'device_id' => $device?->id,
            'count' => $events->count(),
            'event_ids' => $events->pluck('id')->toArray(),
        ]);

        // Match WebSocket PrintOrder event structure for relay device compatibility
        $payload = $events->map(function ($e) {
            return [
                'id' => $e->id,
                'print_event_id' => $e->id,  // Top-level for relay device parsing
                'device_order_id' => $e->device_order_id,
                'device_id' => $e->deviceOrder?->device_id,
                'order_id' => $e->deviceOrder?->order_id,
                'session_id' => $e->deviceOrder?->session_id,
                'event_type' => $e->event_type,
                'print_type' => $e->event_type,
                'meta' => $e->meta,
                'created_at' => $e->created_at instanceof \DateTimeInterface ? $e->created_at->format(DATE_ATOM) : null,
                'tablename' => $e->deviceOrder?->table?->name ?? null,
                'guest_count' => $e->deviceOrder?->guest_count ?? null,
                'order_number' => $e->deviceOrder?->order_number ?? null,
                'order' => $e->deviceOrder ? [
                    'id' => $e->deviceOrder->id,
                    'order_id' => $e->deviceOrder->order_id,
                    'order_number' => $e->deviceOrder->order_number,
                    'device_id' => $e->deviceOrder->device_id,
                    'status' => $e->deviceOrder->status,
                    'total' => $e->deviceOrder->total,
                    'guest_count' => $e->deviceOrder->guest_count,
                    'created_at' => $e->deviceOrder->created_at instanceof \DateTimeInterface ? $e->deviceOrder->created_at->format(DATE_ATOM) : null,
                ] : null,
                'items' => $e->deviceOrder?->items?->map(fn ($it) => [
                    'id' => $it->id,
                    'menu_id' => $it->menu_id,
                    'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? null,
                    'quantity' => $it->quantity,
                    'price' => $it->price,
                    'subtotal' => $it->subtotal,
                ])->values() ?? [],
            ];
        });

        return response()->json([
            'success' => true,
            'count' => $payload->count(),
            'events' => $payload,
        ]);
    }

    /**
     * Acknowledge a PrintEvent as handled by a printer.
     */
    public function ackPrintEvent(AckPrintEventRequest $request, int $id)
    {
        Log::info("[ACK] Received request for print_event_id=$id", [
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);

        // Optional auth for relay device emergency mode
        $device = Auth::guard('device')->user();
        
        Log::info("[ACK] Device auth status", [
            'authenticated' => $device !== null,
            'device_id' => $device?->id,
            'device_name' => $device?->name,
        ]);

        $printerId = $request->input('printer_id');
        $printerName = $request->input('printer_name');
        $bluetoothAddress = $request->input('bluetooth_address');
        $appVersion = $request->input('app_version');
        $printedAt = $request->input('printed_at');

        try {
            $evt = $this->printEventService->getById($id);
            Log::info("[ACK] PrintEvent found", [
                'print_event_id' => $evt->id,
                'order_id' => $evt->device_order_id,
                'already_acked' => $evt->is_acknowledged,
            ]);
        } catch (\Throwable $e) {
            Log::error("[ACK] PrintEvent not found: $id", ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        // Authorize device for this event (passes device for branch check)
        try {
            $this->authorizeDeviceForEvent($evt, $device);
            Log::info("[ACK] Authorization passed");
        } catch (\Throwable $e) {
            Log::error("[ACK] Authorization failed", [
                'print_event_id' => $id,
                'device_id' => $device?->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Check idempotency: if already acknowledged, return success with was_updated=false
        if ($evt->is_acknowledged) {
            Log::info("[ACK] Already acknowledged, returning success");
            return response()->json([
                'success' => true,
                'message' => 'Already acknowledged',
                'data' => [
                    'id' => $evt->id,
                    'was_updated' => false,
                    'acknowledged_by' => $evt->acknowledgedByDevice?->name,
                ],
            ]);
        }

        $res = $this->printEventService->ack($id, $printerId, $printedAt, $device?->id, $printerName);
        Log::info("[ACK] Successfully acknowledged print_event_id=$id", [
            'was_updated' => $res['was_updated'],
        ]);

        // Update device heartbeat (only if authenticated)
        if ($device) {
            $device->last_seen_at = now();
            if ($appVersion) {
                $device->app_version = $appVersion;
            }
            /** @var \App\Models\Device $device */
            $device->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Acknowledged',
            'data' => [
                'id' => $res['print_event']->id,
                'was_updated' => $res['was_updated'],
                'acknowledged_by' => $res['print_event']->acknowledgedByDevice?->name ?? $device?->name ?? 'guest',
            ],
        ]);
    }

    /**
     * Mark a PrintEvent as failed (printer reported error).
     */
    public function failPrintEvent(FailPrintEventRequest $request, int $id)
    {
        // Optional auth for relay device emergency mode
        $device = Auth::guard('device')->user();

        $error = $request->input('error');
        $appVersion = $request->input('app_version');

        try {
            $evt = $this->printEventService->getById($id);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        // Authorize device for this event (passes device for branch check)
        $this->authorizeDeviceForEvent($evt, $device);

        // Check idempotency: if already acknowledged, return success with was_updated=false
        if ($evt->is_acknowledged) {
            return response()->json([
                'success' => true,
                'message' => 'Already acknowledged',
                'data' => [
                    'id' => $evt->id,
                    'attempts' => $evt->attempts,
                    'was_updated' => false,
                    'acknowledged_by' => $evt->acknowledgedByDevice?->name,
                ],
            ]);
        }

        $res = $this->printEventService->fail($id, $error, $device?->id);

        // Update device heartbeat
        $device->last_seen_at = now();
        if ($appVersion) {
            $device->app_version = $appVersion;
        }
        /** @var \App\Models\Device $device */
        $device->save();

        return response()->json([
            'success' => true,
            'message' => 'Marked failed',
            'data' => [
                'id' => $res['print_event']->id,
                'attempts' => $res['print_event']->attempts,
                'was_updated' => $res['was_updated'],
                'acknowledged_by' => $res['print_event']->acknowledgedByDevice?->name ?? $device->name,
            ],
        ]);
    }

    /**
     * Ensure the authenticated device can operate on the provided PrintEvent.
     * Enforcement is branch-level: the device's branch must match the order's branch.
     */
    protected function authorizeDeviceForEvent(PrintEvent $evt, $device = null)
    {
        // Skip authorization if no device provided (guest/emergency mode)
        if (! $device) {
            return true;
        }

        $order = $evt->deviceOrder;
        if (! $order) {
            abort(403, 'Event has no associated order');
        }

        // Branch isolation: only enforce if both device and order have branch_id
        if (isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
            abort(403, 'Device not authorized for this branch');
        }

        return true;
    }

    public function heartbeat(PrinterHeartbeatRequest $request)
    {
        $device = Auth::user();  // Authenticated device from token
        /** @var \Illuminate\Database\Eloquent\Model $device */

        // Validate device_id mismatch (if provided in payload)
        if ($request->filled('device_id') && $request->input('device_id') !== $device->id) {
            abort(403, 'Device ID mismatch');
        }

        // Update Device model (persistent storage)
        $device->last_seen_at = now();

        if ($request->filled('app_version')) {
            $device->app_version = $request->input('app_version');
        }

        if ($request->filled('status')) {
            $device->status = $request->input('status');
        }

        $device->save();

        // Keep cache for real-time dashboard (ephemeral)
        $printerId = $request->input('printer_id');
        Cache::put("printer:heartbeat:{$printerId}", [
            'device_id' => $device->id,
            'printer_id' => $printerId,
            'printer_name' => $request->input('printer_name'),
            'bluetooth_address' => $request->input('bluetooth_address'),
            'session_id' => $request->input('session_id'),
            'last_print_event_id' => $request->input('last_print_event_id'),
            'last_printed_order_id' => $request->input('last_printed_order_id'),
            'last_seen' => now(),
        ], 120);

        $sessionActive = $request->filled('session_id');

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat received',
            'data' => [
                'server_time' => now()->toIso8601String(),
                'session_active' => $sessionActive,
                'device_id' => $device->id,
                'status' => $device->status,
            ],
        ]);
    }
}
