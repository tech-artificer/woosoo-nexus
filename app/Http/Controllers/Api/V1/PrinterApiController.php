<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\DeviceOrder;
// TerminalSession checks removed; sessions are device-local
use App\Http\Requests\MarkOrderPrintedRequest;
use App\Http\Requests\GetUnprintedOrdersRequest;
use App\Http\Requests\MarkOrderPrintedBulkRequest;
use App\Http\Requests\PrinterHeartbeatRequest;
use App\Events\PrintOrder;
use App\Events\PrintRefill;
use App\Events\Order\OrderPrinted;
use App\Models\PrintEvent;
use App\Services\PrintEventService;

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

        // Ensure the authenticated device is allowed to operate on this order (branch-level check)
        $device = $request->user();
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
        $limit = min((int)$request->input('limit', 100), 200);

        // Do not require a canonical TerminalSession in POS. Treat session_id as
        // an opaque device-local identifier; if provided, we'll filter by it below.

        $ordersQuery = DeviceOrder::where('session_id', $sessionId)
            ->where('is_printed', 0)
            ->whereNotIn('status', ['CANCELLED', 'VOIDED'])
            ->when($since, fn($q) => $q->where('created_at', '>', $since))
            ->with(['items', 'table', 'device', 'order'])
            ->orderBy('created_at', 'asc');

        // Enforce branch restriction: only return orders for the authenticated device's branch
        $device = $request->user();
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
                'created_at' => $order->created_at?->toIso8601String(),
                'order' => [
                    'order_id' => $order->order_id,
                    'order_number' => $order->order_number,
                    'guest_count' => $order->guest_count,
                    'created_at' => $order->created_at?->toIso8601String(),
                ],
                'items' => $order->items->map(fn($item) => [
                    'id' => $item->id,
                    'menu_id' => $item->menu_id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'note' => $item->notes ?? null,
                ])->values(),
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
            'message' => count($updated) . ' orders marked as printed',
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
        public function getUnprintedEvents(Request $request)
        {
            $limit = min((int)$request->input('limit', 100), 200);
            $since = $request->input('since');

            $device = $request->user();

            $eventsQuery = PrintEvent::where('is_acknowledged', false)
                ->when($since, fn($q) => $q->where('created_at', '>', $since))
                ->with(['deviceOrder', 'deviceOrder.items'])
                ->orderBy('created_at', 'asc');

            // Restrict to events for the same branch as the authenticated device
            if ($device && isset($device->branch_id)) {
                $eventsQuery->whereHas('deviceOrder', fn($q) => $q->where('branch_id', $device->branch_id));
            }

            // Optionally restrict by session if provided
            if ($request->filled('session_id')) {
                $eventsQuery->whereHas('deviceOrder', fn($q) => $q->where('session_id', $request->input('session_id')));
            }

            $events = $eventsQuery->limit($limit)->get();

            $payload = $events->map(function ($e) {
                return [
                    'id' => $e->id,
                    'device_order_id' => $e->device_order_id,
                    'event_type' => $e->event_type,
                    'meta' => $e->meta,
                    'created_at' => $e->created_at?->toIso8601String(),
                    'order' => $e->deviceOrder ? [
                        'order_id' => $e->deviceOrder->order_id,
                        'order_number' => $e->deviceOrder->order_number,
                        'items' => $e->deviceOrder->items->map(fn($it) => [
                            'menu_id' => $it->menu_id,
                            'quantity' => $it->quantity,
                            'name' => $it->name,
                        ])->values(),
                    ] : null,
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
        public function ackPrintEvent(Request $request, int $id)
        {
                $printerId = $request->input('printer_id');
                $printedAt = $request->input('printed_at');

                try {
                    $evt = $this->printEventService->getById($id);
                } catch (\Throwable $e) {
                    return response()->json(['success' => false, 'message' => 'Event not found'], 404);
                }

                // authorize device for this event
                $this->authorizeDeviceForEvent($evt);

                $res = $this->printEventService->ack($id, $printerId, $printedAt);

                return response()->json([
                    'success' => true,
                    'message' => 'Acknowledged',
                    'data' => [
                        'id' => $res['print_event']->id,
                        'was_updated' => $res['was_updated'],
                    ],
                ]);
        }

        /**
         * Mark a PrintEvent as failed (printer reported error).
         */
        public function failPrintEvent(Request $request, int $id)
        {
                try {
                    $evt = $this->printEventService->getById($id);
                } catch (\Throwable $e) {
                    return response()->json(['success' => false, 'message' => 'Event not found'], 404);
                }

                $this->authorizeDeviceForEvent($evt);

                $res = $this->printEventService->fail($id, $request->input('error'));

                return response()->json([
                    'success' => true,
                    'message' => 'Marked failed',
                    'data' => [
                        'id' => $res['print_event']->id,
                        'attempts' => $res['print_event']->attempts,
                        'was_updated' => $res['was_updated'],
                    ],
                ]);
        }

        /**
         * Ensure the authenticated device can operate on the provided PrintEvent.
         * Enforcement is branch-level: the device's branch must match the order's branch.
         */
        protected function authorizeDeviceForEvent(PrintEvent $evt)
        {
            $device = auth()->user();
            if (! $device) {
                abort(403, 'Unauthenticated device');
            }

            $order = $evt->deviceOrder;
            if (! $order) {
                abort(403, 'Event has no associated order');
            }

            if (isset($device->branch_id) && isset($order->branch_id) && $device->branch_id !== $order->branch_id) {
                abort(403, 'Device not authorized for this branch');
            }

            return true;
        }

    public function heartbeat(PrinterHeartbeatRequest $request)
    {
        $printerId = $request->input('printer_id');
        Cache::put("printer:heartbeat:{$printerId}", [
            'printer_id' => $printerId,
            'printer_name' => $request->input('printer_name'),
            'bluetooth_address' => $request->input('bluetooth_address'),
            'app_version' => $request->input('app_version'),
            'session_id' => $request->input('session_id'),
            'last_printed_order_id' => $request->input('last_printed_order_id'),
            'last_seen' => now(),
        ], now()->addMinutes(2));

        $sessionActive = $request->filled('session_id');

        return response()->json([
            'success' => true,
            'message' => 'Heartbeat received',
            'data' => [
                'server_time' => now()->toIso8601String(),
                'session_active' => $sessionActive,
            ],
        ]);
    }
}
