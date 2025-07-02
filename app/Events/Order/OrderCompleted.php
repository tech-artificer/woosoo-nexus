<?php

namespace App\Events\Order;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\DeviceOrder;
use App\Http\Resources\OrderResource;

class OrderCompleted implements ShouldBroadcastNow
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
        return [
              new Channel('orders'),
        ];
    }
    /**
     * Get the data to broadcast for the notification.
     *
     * @return OrderResource
     */
    public function broadcastWith()
    {   
        // return (new OrderResource($this->order))->toArray(request());
        // $order = new OrderResource($this->order);
        // return $order->toArray(request());
        return [
            'id' => $this->order->id,
            'order_id' => $this->order->order_id,
            'status' => $this->order->status
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'order.completed'; // Custom event name for frontend to listen to
    }
}
