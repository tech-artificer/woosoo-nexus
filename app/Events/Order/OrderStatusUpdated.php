<?php

namespace App\Events\Order;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

use App\Models\DeviceOrder;
use App\Http\Resources\OrderResource;
use App\Enums\OrderStatus;

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
        return [ 
            new Channel("Device.{$this->order->device_id}"),
            new Channel('admin.orders'),
        ];
    }

    public function broadcastWith()
    {
        $this->order->loadMissing(['device', 'table']);
        $table = $this->order->device?->table ?? $this->order->table;

        return [
            'order' => [
                'id' => $this->order->id,
                'order_id' => $this->order->order_id,
                'order_number' => $this->order->order_number,
                'device_id' => $this->order->device_id,
                'table_id' => $this->order->table_id,
                'branch_id' => $this->order->branch_id,
                'status' => $this->order->status,
                'is_printed' => $this->order->is_printed ?? false,
                'total' => $this->order->total,
                'created_at' => $this->order->created_at?->toIso8601String(),
                'updated_at' => $this->order->updated_at?->toIso8601String(),
                'device' => $this->order->device ? [
                    'id' => $this->order->device->id,
                    'name' => $this->order->device->name,
                ] : null,
                'table' => $table ? [
                    'id' => $table->id,
                    'name' => $table->name,
                ] : null,
            ],
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
