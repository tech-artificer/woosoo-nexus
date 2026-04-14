<?php

namespace App\Events\Order;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\DeviceOrder;
use App\Helpers\OrderBroadcastPayload;

class OrderVoided implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $deviceOrder;

    /**
     * Create a new event instance.
     */
    public function __construct(DeviceOrder $deviceOrder)
    {
        $this->deviceOrder = $deviceOrder;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orders.' . $this->deviceOrder->order_id),
            new Channel('admin.orders'),
        ];
    }
    /**
     * Get the data to broadcast for the notification.
     *
     * @return OrderResource
     */
    public function broadcastWith()
    {   
        return [
            'order' => OrderBroadcastPayload::make($this->deviceOrder),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'order.voided'; // Custom event name for frontend to listen to
    }
}
