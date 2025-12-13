<?php

namespace App\Events\Order;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\DeviceOrder;

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
        $this->deviceOrder->load(['device', 'table', 'serviceRequests', 'items.menu']);
        $table = $this->deviceOrder->device?->table ?? $this->deviceOrder->table;

        return [
            'order' => [
                'id' => $this->deviceOrder->id,
                'order_id' => $this->deviceOrder->order_id,
                'order_number' => $this->deviceOrder->order_number,
                'device_id' => $this->deviceOrder->device_id,
                'table_id' => $this->deviceOrder->table_id,
                'branch_id' => $this->deviceOrder->branch_id,
                'session_id' => $this->deviceOrder->session_id,
                'status' => $this->deviceOrder->status,
                'items' => $this->deviceOrder->items?->map(fn($it) => [
                    'id' => $it->id,
                    'name' => $it->menu?->receipt_name ?? $it->menu?->name ?? null,
                    'quantity' => $it->quantity ?? null,
                    'price' => $it->price ?? null,
                    'is_refill' => $it->is_refill ?? false,
                ])->values()->all() ?? [],
                'total' => $this->deviceOrder->total ?? null,
                'tax' => $this->deviceOrder->tax ?? null,
                'discount' => $this->deviceOrder->discount ?? null,
                'sub_total' => $this->deviceOrder->sub_total ?? null,
                'guest_count' => $this->deviceOrder->guest_count ?? null,
                'created_at' => $this->deviceOrder->created_at?->toDateTimeString() ?? null,
                'updated_at' => $this->deviceOrder->updated_at?->toDateTimeString() ?? null,
                'is_printed' => $this->deviceOrder->is_printed ?? false,
                'device' => $this->deviceOrder->device ? [
                    'id' => $this->deviceOrder->device->id,
                    'name' => $this->deviceOrder->device->name,
                ] : null,
                'table' => $table ? [
                    'id' => $table->id,
                    'name' => $table->name,
                ] : null,
                'serviceRequests' => $this->deviceOrder->serviceRequests ?? [],
            ]
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
