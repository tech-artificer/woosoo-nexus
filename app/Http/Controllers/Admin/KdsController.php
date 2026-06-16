<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Broadcasting\OrderBroadcaster;
use App\Enums\OrderStatus;
use App\Helpers\OrderBroadcastPayload;
use App\Http\Controllers\Controller;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Services\AuditLogService;
use App\Services\Pos\PosOrderService;
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
        private readonly PosOrderService $orderService,
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
        $reachable = $this->posConnection->isReachable();
        $with = $reachable
            ? ['device.table', 'table', 'items.menu']
            : ['device', 'items'];

        $orders = DeviceOrder::with($with)
            ->whereNotIn('status', array_map(fn ($s) => $s->value, self::HIDDEN_STATUSES))
            ->orderBy('created_at')
            ->get();

        return Inertia::render('KDS/Display', [
            'title' => 'Kitchen Display',
            'initialTickets' => $orders->map(fn ($order) => $this->toTicket($order, $reachable))->values(),
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
                $order->saveQuietly();
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
                // saveQuietly() suppresses observer events; the controller's explicit
                // statusChanged() call is the sole broadcast site for these transitions.
                $locked->status = OrderStatus::READY;
                $locked->saveQuietly();
                $locked->status = OrderStatus::SERVED;
                $locked->saveQuietly();
                $next = OrderStatus::SERVED;

                return;
            }

            $nextStatus = $this->nextStatus($current);

            if ($nextStatus === null) {
                $gateMessage = 'No advance available from current state.';

                return;
            }

            $locked->status = $nextStatus;
            $locked->saveQuietly();
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

            // Stale-state re-check under lock: recall is SERVED→IN_PROGRESS only.
            // Bypass canTransitionTo() — that gate intentionally excludes this edge
            // so only KdsController::recall() can drive it.
            if ($fresh->status !== OrderStatus::SERVED) {
                $gateMessage = 'Order state changed concurrently; please retry.';

                return;
            }

            // Re-check cap under lock: closes the TOCTOU window between the pre-check above and here.
            if (($fresh->recalled ?? 0) >= self::MAX_RECALLS) {
                $gateMessage = 'Maximum recalls reached for this order.';

                return;
            }

            // Write directly via DB to bypass the model setter (which enforces
            // canTransitionTo and would reject this KDS-exclusive edge).
            DB::table('device_orders')->where('id', $fresh->id)->update([
                'status' => OrderStatus::IN_PROGRESS->value,
                'recalled' => ($fresh->recalled ?? 0) + 1,
                'updated_at' => now(),
            ]);
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

    public function void(Request $request, DeviceOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $gateMessage = null;

        DB::transaction(function () use ($order, $validated, &$gateMessage) {
            $locked = DeviceOrder::lockForUpdate()->findOrFail($order->id);

            // Terminal-state pre-check MUST run before assigning status: DeviceOrder::setStatusAttribute
            // runs canTransitionTo() and throws on a terminal→VOIDED set. Guard here so a
            // concurrent finalisation returns a clean 422 instead of a 500.
            if (! $locked->status->canTransitionTo(OrderStatus::VOIDED)) {
                $gateMessage = 'Order cannot be voided from its current state.';

                return;
            }

            $locked->status = OrderStatus::VOIDED;
            $locked->void_reason = $validated['reason'];
            // saveQuietly() — we broadcast the terminal event explicitly below.
            $locked->saveQuietly();
        });

        if ($gateMessage !== null) {
            return response()->json(['message' => $gateMessage], 422);
        }

        // Best-effort POS void: PosOrderService::voidOrder() takes the Krypton order id
        // (order_id), not the local PK, and has no internal POS-down guard. A failure must
        // not roll back the local VOID, so wrap it and continue on error.
        try {
            if (! empty($order->order_id)) {
                $this->orderService->voidOrder((string) $order->order_id);
            }
        } catch (\Throwable $e) {
            Log::warning('[KDS] POS void failed; local order voided regardless', [
                'order_id' => $order->id,
                'krypton_order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);
        }

        AuditLogService::adminAction($request, 'kds.order_voided', (int) $request->user()->id, [
            'order_id' => $order->id,
            'krypton_order_id' => $order->order_id,
            'reason' => $validated['reason'],
        ]);

        Log::info('[KDS] void', ['order_id' => $order->id, 'admin_id' => auth()->id()]);

        $order->refresh();
        $order->loadMissing(['items', 'device', 'serviceRequests']);
        app(OrderBroadcaster::class)->finalized($order, 'voided');

        // See advance(): full board payload for optimistic client apply.
        return response()->json([
            'status' => OrderStatus::VOIDED->value,
            'order' => OrderBroadcastPayload::make($order),
            'server_now' => (int) (microtime(true) * 1000),
        ]);
    }

    private function toTicket(DeviceOrder $order, bool $posReachable): array
    {
        // Only access POS-backed relations if POS is reachable; prevent lazy-load 500s when POS is down.
        $table = $posReachable ? ($order->device?->table ?? $order->table) : null;
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
            'state' => $order->status->kdsState(),
            'items' => $items->map(fn ($it) => [
                'id' => (string) $it->id,
                'qty' => (int) ($it->quantity ?? 1),
                'name' => $posReachable ? ($it->menu?->receipt_name ?? $it->menu?->name ?? $it->name ?? '') : ($it->name ?? ''),
                'done' => (bool) ($it->done ?? false),
                'notes' => $it->notes ?? null,
            ])->values()->all(),
            'recalled' => $order->recalled ?? 0,
            'voidReason' => $order->void_reason ?? null,
        ];
    }
}
