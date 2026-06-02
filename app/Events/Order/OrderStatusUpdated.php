<?php

namespace App\Events\Order;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

use App\Models\DeviceOrder;
use App\Helpers\OrderBroadcastPayload;

class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    /**
     * Create a new event instance.
     */
    public function __construct(DeviceOrder $order)
    {
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // NEX-CASE-013: tablet subscribes to `orders.{order_id}` (per
        // `contracts/websocket-events.contract.md`). Without this channel the
        // tablet never receives status transitions — a silent terminal-event
        // drop in the SessionReset family. `device.{device_id}` is kept for
        // legacy listeners; removal is a breaking change tracked separately.
        return [
            new Channel("orders.{$this->order->order_id}"),
            new Channel("device.{$this->order->device_id}"),
            new Channel('admin.orders'),
        ];
    }

    public function broadcastWith()
    {
        return [
            'order' => OrderBroadcastPayload::make($this->order),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'order.updated'; // Custom event name for frontend to listen to
    }
}
