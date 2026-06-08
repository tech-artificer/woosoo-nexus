<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Broadcasting\OrderBroadcaster;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class KdsController extends Controller
{
    private const HIDDEN_STATUSES = [
        OrderStatus::COMPLETED,
        OrderStatus::CANCELLED,
        OrderStatus::ARCHIVED,
    ];

    private const TERMINAL_ITEM_STATUSES = [
        OrderStatus::SERVED,
        OrderStatus::COMPLETED,
        OrderStatus::CANCELLED,
        OrderStatus::VOIDED,
        OrderStatus::ARCHIVED,
    ];

    public function index(Request $request): Response
    {
        $orders = DeviceOrder::with(['device.table', 'table', 'items.menu'])
            ->whereNotIn('status', array_map(fn ($s) => $s->value, self::HIDDEN_STATUSES))
            ->orderBy('created_at')
            ->get();

        return Inertia::render('KDS/Display', [
            'title' => 'Kitchen Display',
            'initialTickets' => $orders->map(fn ($order) => $this->toTicket($order))->values(),
        ]);
    }

    public function advance(DeviceOrder $order): JsonResponse
    {
        $order->loadMissing('items');
        $current = $order->status;

        // Auto-advance pending through confirmed (pending is transient)
        if ($current === OrderStatus::PENDING) {
            DB::transaction(function () use ($order) {
                $order->status = OrderStatus::CONFIRMED;
                $order->save();
            });
            $order->refresh();
            $current = OrderStatus::CONFIRMED;
        }

        $next = $this->nextStatus($current);

        if ($next === null) {
            return response()->json(['message' => 'No advance available from current state.'], 422);
        }

        $gateMessage = null;

        DB::transaction(function () use ($order, $current, $next, &$gateMessage) {
            // Lock order row — serialises concurrent advance() calls and toggleItem() writes.
            DeviceOrder::lockForUpdate()->find($order->id);

            // Mark Ready gate: re-check items under lock to eliminate TOCTOU with toggleItem().
            if ($current === OrderStatus::IN_PROGRESS) {
                $hasUndone = DeviceOrderItems::where('order_id', $order->id)
                    ->where('done', false)
                    ->lockForUpdate()
                    ->exists();

                if ($hasUndone) {
                    $gateMessage = 'All items must be marked done before advancing to Ready.';

                    return;
                }
            }

            $order->status = $next;
            $order->save();
        });

        if ($gateMessage !== null) {
            return response()->json(['message' => $gateMessage], 422);
        }

        Log::info('[KDS] advance', ['order_id' => $order->id, 'to' => $next->value, 'admin_id' => auth()->id()]);

        $order->load(['device.table', 'table', 'items.menu', 'serviceRequests']);
        app(OrderBroadcaster::class)->statusChanged($order);

        return response()->json(['status' => $next->value]);
    }

    public function toggleItem(DeviceOrderItems $item): JsonResponse
    {
        $item->loadMissing('device_order');

        if (in_array($item->device_order->status, self::TERMINAL_ITEM_STATUSES)) {
            return response()->json(['message' => 'Cannot toggle items on a completed or closed order.'], 422);
        }

        DB::transaction(function () use ($item) {
            // Lock the parent order row so concurrent advance() must wait until this write commits.
            DeviceOrder::lockForUpdate()->find($item->order_id);

            $item->done = ! (bool) ($item->done ?? false);
            $item->done_at = $item->done ? now() : null;
            $item->save();
        });

        Log::info('[KDS] toggle item', ['item_id' => $item->id, 'done' => $item->done, 'admin_id' => auth()->id()]);

        app(OrderBroadcaster::class)->itemToggled($item);

        return response()->json([
            'done' => (bool) $item->done,
            'done_at' => $item->done_at?->toIso8601String(),
        ]);
    }

    private function nextStatus(OrderStatus $status): ?OrderStatus
    {
        return match ($status) {
            OrderStatus::CONFIRMED => OrderStatus::IN_PROGRESS,
            OrderStatus::IN_PROGRESS => OrderStatus::READY,
            OrderStatus::READY => OrderStatus::SERVED,
            default => null,
        };
    }

    private function toTicket(DeviceOrder $order): array
    {
        $table = $order->device?->table ?? $order->table;
        $items = $order->items ?? collect();
        $isRefill = $items->isNotEmpty() && $items->every(fn ($it) => (bool) ($it->is_refill ?? false));
        $now = now();
        $createdAt = $order->created_at;
        $issuedAtMs = $createdAt ? $createdAt->timestamp * 1000 : $now->timestamp * 1000;
        $elapsed = $createdAt ? (int) $createdAt->diffInSeconds($now) : 0;
        $isTerminal = in_array($order->status, [OrderStatus::SERVED, OrderStatus::VOIDED]);
        $frozenElapsed = ($isTerminal && $order->updated_at && $createdAt)
            ? (int) $createdAt->diffInSeconds($order->updated_at)
            : null;

        return [
            'id' => (string) $order->id,
            'table' => $table?->name ?? '—',
            'type' => $isRefill ? 'refill' : 'initial',
            'issued' => $createdAt?->format('g:i A') ?? '',
            'issuedAt' => $issuedAtMs,
            'elapsed' => $elapsed,
            'frozenElapsed' => $frozenElapsed,
            'state' => $this->toKdsState($order->status),
            'items' => $items->map(fn ($it) => [
                'id' => (string) $it->id,
                'qty' => (int) ($it->quantity ?? 1),
                'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? $it->name ?? '',
                'done' => (bool) ($it->done ?? false),
            ])->values()->all(),
            'recalled' => $order->recalled ?? 0,
            'voidReason' => null,
        ];
    }

    private function toKdsState(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PENDING,
            OrderStatus::CONFIRMED => 'new',
            OrderStatus::IN_PROGRESS => 'preparing',
            OrderStatus::READY => 'ready',
            OrderStatus::SERVED => 'served',
            OrderStatus::VOIDED => 'voided',
            default => 'new',
        };
    }
}
