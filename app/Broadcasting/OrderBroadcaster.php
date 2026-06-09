<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Events\Kds\ItemToggled;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderCreated;
use App\Events\Order\OrderDetailsUpdated;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderVoided;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Services\BroadcastService;
use InvalidArgumentException;

/**
 * NEX-CASE-013: intent-based broadcast boundary for order lifecycle events.
 *
 * Existing scattered dispatch sites (DeviceOrderObserver, ForceEndSession,
 * MonitoringController, DeviceOrderApiController, ConsumePosPaymentStatusEvents)
 * continue to call event classes directly for now — migrating them is a
 * follow-up to avoid a 5-site refactor risking regressions in this PR. New
 * consumers (e.g. `ConsumePosOrderDetailEvents`) call this broadcaster, so the
 * boundary lands without disturbing the proven dispatch paths.
 *
 * Every method delegates to `BroadcastService::safeBroadcast` so a transient
 * Reverb failure becomes a logged warning, not an uncaught exception that
 * leaks back into the consumer's failure-counter logic.
 *
 * @see contracts/websocket-events.contract.md
 */
class OrderBroadcaster
{
    public function __construct(private readonly BroadcastService $broadcastService) {}

    public function created(DeviceOrder $order): void
    {
        $this->broadcastService->safeBroadcast(new OrderCreated($order));
    }

    public function statusChanged(DeviceOrder $order): void
    {
        $this->broadcastService->safeBroadcast(new OrderStatusUpdated($order));
    }

    public function detailsUpdated(DeviceOrder $order): void
    {
        $this->broadcastService->safeBroadcast(new OrderDetailsUpdated($order));
    }

    public function itemToggled(DeviceOrderItems $item): void
    {
        $this->broadcastService->safeBroadcast(new ItemToggled($item));
    }

    /**
     * Dispatch the appropriate terminal event for an order finalization.
     *
     * @param  string  $status  one of: completed, voided, cancelled
     */
    public function finalized(DeviceOrder $order, string $status): void
    {
        $event = match ($status) {
            'completed' => new OrderCompleted($order),
            'voided' => new OrderVoided($order),
            'cancelled' => new OrderCancelled($order),
            default => throw new InvalidArgumentException(
                "OrderBroadcaster::finalized cannot route status [{$status}]"
            ),
        };

        $this->broadcastService->safeBroadcast($event);
    }
}
