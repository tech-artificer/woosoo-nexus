<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\DeviceOrder;

class PrintOrder implements ShouldBroadcastNow 
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DeviceOrder $deviceOrder;
    /**
     * Create a new event instance.
     */
    public function __construct(DeviceOrder $deviceOrder)
    {
        // Eager-load menu relationships to prevent N+1 queries in broadcastWith()
        $this->deviceOrder = $deviceOrder->loadMissing(['items.menu', 'table']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('admin.print'),
        ];
    }

    public function broadcastWith() : array
    {
        return [
            'print_event_id' => $this->deviceOrder->printEvent?->id,
            'device_id' => $this->deviceOrder->device_id,
            'order_id' => $this->deviceOrder->order_id,
            'session_id' => $this->deviceOrder->session_id,
            'print_type' => 'INITIAL',
            'refill_number' => null,
            'tablename' => $this->deviceOrder->table?->name,
            'created_at' => $this->deviceOrder->created_at->toIso8601String(),
            'order' => $this->deviceOrder->only([
                'id',
                'order_id',
                'order_number',
                'device_id',
                'status',
                'created_at',
                'guest_count',
                'total',
                'is_printed',
                'printed_at',
                'printed_by',
            ]),
            'items' => $this->deviceOrder->items->map(fn ($item) => [
                'id' => $item->id,
                'menu_id' => $item->menu_id,
                'name' => $item->menu?->receipt_name ?? $item->menu?->name ?? null,
                'quantity' => $item->quantity ?? null,
                'price' => $item->price ?? null,
                'subtotal' => $item->subtotal ?? null,
                'note' => $item->notes ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'order.printed';
    }
}
