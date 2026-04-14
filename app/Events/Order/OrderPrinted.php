<?php

namespace App\Events\Order;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\DeviceOrder;

class OrderPrinted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DeviceOrder $deviceOrder;

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
            new Channel('admin.orders'),
            new Channel('orders.' . $this->deviceOrder->order_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $this->deviceOrder->loadMissing(['device', 'table', 'printEvent']);
        $table = $this->deviceOrder->device?->table ?? $this->deviceOrder->table;

        return [
            'print_event_id' => $this->deviceOrder->printEvent?->id,
            'device_id' => $this->deviceOrder->device_id,
            'order_id' => $this->deviceOrder->order_id,
            'session_id' => $this->deviceOrder->session_id,
            'print_type' => 'INITIAL',
            'refill_number' => null,
            'tablename' => $table?->name,
            'created_at' => $this->deviceOrder->created_at?->toIso8601String(),
            'order' => [
                'id' => $this->deviceOrder->id,
                'order_id' => $this->deviceOrder->order_id,
                'order_number' => $this->deviceOrder->order_number,
                'device_id' => $this->deviceOrder->device_id,
                'table_id' => $this->deviceOrder->table_id,
                'branch_id' => $this->deviceOrder->branch_id,
                'status' => $this->deviceOrder->status,
                'is_printed' => $this->deviceOrder->is_printed ?? false,
                'printed_at' => $this->deviceOrder->printed_at?->toIso8601String(),
                'total' => $this->deviceOrder->total,
                'created_at' => $this->deviceOrder->created_at?->toIso8601String(),
                'updated_at' => $this->deviceOrder->updated_at?->toIso8601String(),
                'device' => $this->deviceOrder->device ? [
                    'id' => $this->deviceOrder->device->id,
                    'name' => $this->deviceOrder->device->name,
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
     */
    public function broadcastAs(): string
    {
        return 'order.printed';
    }
}
