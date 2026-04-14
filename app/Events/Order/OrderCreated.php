<?php

namespace App\Events\Order;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\DeviceOrder;
use App\Helpers\OrderBroadcastPayload;

class OrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DeviceOrder $deviceOrder;

    public function __construct(DeviceOrder $deviceOrder)
    {
        $this->deviceOrder = $deviceOrder;
    }

    /**
     * Channels this event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('admin.orders'),
            new Channel('orders.' . ($this->deviceOrder->order_id ?? '')),
            new Channel('device.' . ($this->deviceOrder->device_id ?? '')),
        ];
    }

    /**
     * Payload sent to clients.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'order' => OrderBroadcastPayload::make($this->deviceOrder),
        ];
    }

    /**
     * Optional: set a short event name used by Echo listeners.
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }
}
