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
// use App\Http\Resources\OrderResource;

class OrderCreated implements ShouldBroadcastNow
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
    public function broadcastOn() : array
    {
        // return new PrivateChannel('device.' . $this->order->device_id);
        // Broadcast to all admins via a dedicated channel

        return [
            new Channel('orders.' . $this->deviceOrder->order_id),
            //  new PrivateChannel('orders.' . $this->deviceOrder->device_id);
            new PrivateChannel('admin.orders'),
        ];
    }

    /**
     * Get the data to broadcast for the notification.
     *
     */
    public function broadcastWith() : array
    {   
        return [
            'order' => $this->deviceOrder->only([
                'id', 
                'order_id', 
                'order_number', 
                'device_id', 
                'status'
            ])
        ];
        // return (new OrderResource($this->order))->toArray(request());
        // $order = new OrderResource($this->order);
        // return $order->toArray(request());
        // return [
        //     'id' => $this->deviceOrder->id,
        //     'order_number' => $this->deviceOrder->order_number,
        //     'device_id' => $this->deviceOrder->device_id,
        //     'status' => $this->deviceOrder->status
        // ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'order.created'; 
    }
}
