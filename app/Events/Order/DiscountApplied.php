<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Models\DeviceOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a POS discount transitions from zero to a positive value on an
 * active order. Distinct from OrderDetailsUpdated so the tablet can show a
 * dedicated "Discount Applied" banner without re-processing general detail diffs.
 *
 * Channel: orders.{order_id} — the tablet is already subscribed once an order exists.
 * Payload carries totals only; no perk metadata (no Discount model in Nexus).
 *
 * @see contracts/websocket-events.contract.md
 */
class DiscountApplied implements ShouldBroadcastNow
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
        ];
    }

    public function broadcastAs(): string
    {
        return 'discount.applied';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->order_id,
            'totals' => [
                'subtotal' => (float) ($this->order->subtotal ?? $this->order->sub_total ?? 0),
                'discount_total' => (float) ($this->order->discount ?? 0),
                'total' => (float) ($this->order->total ?? 0),
            ],
        ];
    }
}
