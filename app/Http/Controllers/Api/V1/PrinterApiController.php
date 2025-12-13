<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\DeviceOrder;
use App\Models\Krypton\TerminalSession;
use App\Http\Requests\MarkOrderPrintedRequest;
use App\Http\Requests\GetUnprintedOrdersRequest;
use App\Http\Requests\MarkOrderPrintedBulkRequest;
use App\Http\Requests\PrinterHeartbeatRequest;
use App\Events\PrintOrder;
use App\Events\PrintRefill;
use App\Events\Order\OrderPrinted;

class PrinterApiController extends Controller
{
    public function markPrinted(MarkOrderPrintedRequest $request, int $orderId)
    {
        $deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
        if (! $deviceOrder) {
            return response()->json(['success' => false, 'message' => 'Order not found', 'data' => null], 404);
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
        $deviceOrder->printed_at = $request->input('printed_at', now());
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
        $limit = min($request->input('limit', 50), 100);

        $session = TerminalSession::find($sessionId);
        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found or expired',
                'session_id' => $sessionId,
                'count' => 0,
                'orders' => [],
            ], 404);
        }

        $orders = DeviceOrder::where('session_id', $sessionId)
            ->where('is_printed', 0)
            ->whereNotIn('status', ['CANCELLED', 'VOIDED'])
            ->when($since, fn($q) => $q->where('created_at', '>', $since))
            ->with(['items', 'table', 'device', 'order'])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

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
        $printedAt = $request->input('printed_at', now());
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

        $sessionActive = false;
        if ($sessionId = $request->input('session_id')) {
            $session = TerminalSession::find($sessionId);
            $sessionActive = $session && $session->status === 'ACTIVE';
        }

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
