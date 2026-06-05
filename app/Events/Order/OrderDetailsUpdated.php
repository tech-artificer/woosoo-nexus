<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Helpers\OrderBroadcastPayload;
use App\Models\DeviceOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NEX-CASE-013: POS-originated order-detail change observed by the
 * `woosoo_order_detail_outbox` consumer.
 *
 * Distinct from `OrderStatusUpdated` (status transitions) and `OrderCreated`
 * (initial dispatch). Tablet redraws totals/items/guest_count without
 * mutating its locally-perceived order state machine.
 *
 * Channels: only the canonical `orders.{order_id}` channel and `admin.orders`.
 * No `device.{device_id}` fan-out: this event is born from the per-order
 * outbox and the tablet's subscription is keyed on `order_id`.
 *
 * @see contracts/websocket-events.contract.md
 */
class OrderDetailsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DeviceOrder $order) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orders.'.$this->order->order_id),
            new Channel('admin.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.details.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order' => OrderBroadcastPayload::make($this->order),
        ];
    }
}
