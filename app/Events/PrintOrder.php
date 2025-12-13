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
            new Channel('admin.print'),
        ];
    }

    public function broadcastWith() : array
    {
        return [
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
            'tablename' => $this->deviceOrder->table?->name ?? null,
            'items' => $this->deviceOrder->items()->map(fn ($item) => [
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
