<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Broadcasting\OrderBroadcaster;
use App\Enums\OrderStatus;
use App\Helpers\OrderBroadcastPayload;
use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Services\PosConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class KdsController extends Controller
{
    public function __construct(
        private readonly PosConnectionService $posConnection,
    ) {}

    private const MAX_RECALLS = 5;

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
        $with = $this->posConnection->isReachable()
            ? ['device.table', 'table', 'items.menu']
            : ['device', 'items'];

        $orders = DeviceOrder::with($with)
            ->whereNotIn('status', array_map(fn ($s) => $s->value, self::HIDDEN_STATUSES))
            ->orderBy('created_at')
            ->get();

        return Inertia::render('KDS/Display', [
            'title' => 'Kitchen Display',
            'initialTickets' => $orders->map(fn ($order) => $this->toTicket($order))->values(),
            'serverNow' => (int) (microtime(true) * 1000),
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

        $gateMessage = null;
        $next = null;

        DB::transaction(function () use ($order, $current, &$gateMessage, &$next) {
            // Re-read with lock so we both serialise concurrent writers and write to the current DB row.
            $locked = DeviceOrder::lockForUpdate()->findOrFail($order->id);

            // Guard: concurrent advance() already moved this order; our $current is stale.
            if ($locked->status !== $current) {
                $gateMessage = 'Order state changed concurrently; please retry.';

                return;
            }

            if ($current === OrderStatus::IN_PROGRESS) {
                $hasUndone = DeviceOrderItems::where('order_id', $order->id)
                    ->where('done', false)
                    ->lockForUpdate()
                    ->exists();

                if ($hasUndone) {
                    $gateMessage = 'All items must be marked done before marking as served.';

                    return;
                }

                // Kitchen-facing single action: in_progress → ready → served.
                // saveQuietly() for the READY step prevents a spurious observer broadcast;
                // only the SERVED save triggers the afterCommit dispatch.
                $locked->status = OrderStatus::READY;
                $locked->saveQuietly();
                $locked->status = OrderStatus::SERVED;
                $locked->save();
                $next = OrderStatus::SERVED;

                return;
            }

            $nextStatus = $this->nextStatus($current);

            if ($nextStatus === null) {
                $gateMessage = 'No advance available from current state.';

                return;
            }

            $locked->status = $nextStatus;
            $locked->save();
            $next = $nextStatus;
        });

        if ($gateMessage !== null) {
            return response()->json(['message' => $gateMessage], 422);
        }

        if ($next === null) {
            return response()->json(['message' => 'No advance available from current state.'], 422);
        }

        Log::info('[KDS] advance', ['order_id' => $order->id, 'to' => $next->value, 'admin_id' => auth()->id()]);

        $order->refresh();
        // Preload app-DB relations only. Table/menu (POS connection) are loaded — and
        // guarded — inside OrderBroadcastPayload so a POS outage can't 500 this action.
        $order->loadMissing(['items', 'device', 'serviceRequests']);
        app(OrderBroadcaster::class)->statusChanged($order);

        // Return the full board payload so the client can apply optimistically and not
        // wait for the Echo broadcast (broadcast-down resilience + faster perceived UI).
        return response()->json([
            'status' => $next->value,
            'order' => OrderBroadcastPayload::make($order),
            'server_now' => (int) (microtime(true) * 1000),
        ]);
    }

    public function toggleItem(DeviceOrderItems $item): JsonResponse
    {
        $gateMessage = null;

        DB::transaction(function () use (&$item, &$gateMessage) {
            // Re-read order under lock; serialises with concurrent advance() and re-checks terminal status.
            $order = DeviceOrder::lockForUpdate()->findOrFail($item->order_id);

            if (in_array($order->status, self::TERMINAL_ITEM_STATUSES)) {
                $gateMessage = 'Cannot toggle items on a completed or closed order.';

                return;
            }

            // Re-read the item under lock so the flip is computed from the committed
            // state, not the possibly-stale route-bound instance. Two overlapping
            // toggles then serialise instead of both inverting the same old value.
            $lockedItem = DeviceOrderItems::lockForUpdate()->findOrFail($item->id);
            $lockedItem->done = ! (bool) ($lockedItem->done ?? false);
            $lockedItem->done_at = $lockedItem->done ? now() : null;
            $lockedItem->save();

            $item = $lockedItem;
        });

        if ($gateMessage !== null) {
            return response()->json(['message' => $gateMessage], 422);
        }

        Log::info('[KDS] toggle item', ['item_id' => $item->id, 'done' => $item->done, 'admin_id' => auth()->id()]);

        $item->loadMissing('device_order');
        app(OrderBroadcaster::class)->itemToggled($item);

        // Echo the broadcast shape so the client can dispatch the same applyItemToggle path
        // it uses for live events — keeps optimistic and Echo paths idempotent.
        return response()->json([
            'item_id' => $item->id,
            'order_id' => $item->order_id,
            'done' => (bool) $item->done,
            'done_at' => $item->done_at?->toIso8601String(),
            'server_now' => (int) (microtime(true) * 1000),
        ]);
    }

    private function nextStatus(OrderStatus $status): ?OrderStatus
    {
        return match ($status) {
            OrderStatus::CONFIRMED => OrderStatus::IN_PROGRESS,
            OrderStatus::READY => OrderStatus::SERVED,
            default => null,
        };
    }

    public function recall(DeviceOrder $order): JsonResponse
    {
        // Voided orders require a new ticket — give a specific message rather than the generic one below.
        if ($order->status === OrderStatus::VOIDED) {
            return response()->json(['message' => 'Cannot recall voided order.'], 422);
        }

        // Recall is served→in_progress only; other paths to in_progress go through advance().
        if ($order->status !== OrderStatus::SERVED) {
            return response()->json(['message' => 'Order cannot be recalled from its current state.'], 422);
        }

        if (($order->recalled ?? 0) >= self::MAX_RECALLS) {
            return response()->json(['message' => 'Maximum recalls reached for this order.'], 422);
        }

        $gateMessage = null;

        DB::transaction(function () use ($order, &$gateMessage) {
            $fresh = DeviceOrder::lockForUpdate()->findOrFail($order->id);

            // Stale-state re-check: concurrent request already moved this order.
            if (! $fresh->status->canTransitionTo(OrderStatus::IN_PROGRESS)) {
                $gateMessage = 'Order state changed concurrently; please retry.';

                return;
            }

            // Re-check cap under lock: closes the TOCTOU window between the pre-check above and here.
            if (($fresh->recalled ?? 0) >= self::MAX_RECALLS) {
                $gateMessage = 'Maximum recalls reached for this order.';

                return;
            }

            $fresh->status = OrderStatus::IN_PROGRESS;
            $fresh->recalled = ($fresh->recalled ?? 0) + 1;
            $fresh->save();
        });

        if ($gateMessage !== null) {
            return response()->json(['message' => $gateMessage], 422);
        }

        Log::info('[KDS] recall', ['order_id' => $order->id, 'from' => OrderStatus::SERVED->value, 'admin_id' => auth()->id()]);

        $order->refresh();
        $order->loadMissing(['items', 'device', 'serviceRequests']);
        app(OrderBroadcaster::class)->statusChanged($order);

        // See advance(): full board payload for optimistic client apply.
        return response()->json([
            'status' => OrderStatus::IN_PROGRESS->value,
            'order' => OrderBroadcastPayload::make($order),
            'server_now' => (int) (microtime(true) * 1000),
        ]);
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
        // SERVED is recallable, so freeze the elapsed timer for SERVED and VOIDED only.
        $isFullyTerminal = in_array($order->status, [OrderStatus::SERVED, OrderStatus::VOIDED]);
        $frozenElapsed = ($isFullyTerminal && $order->updated_at && $createdAt)
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
            OrderStatus::READY => 'preparing',
            OrderStatus::SERVED => 'served',
            OrderStatus::VOIDED => 'voided',
            default => 'new',
        };
    }
}
