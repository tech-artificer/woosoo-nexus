<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\DeviceOrder;

class PrintRefill implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $deviceOrder;
    public $items;

    public function __construct(DeviceOrder $deviceOrder = null, array $items = [])
    {
        $this->deviceOrder = $deviceOrder;
        $this->items = $items;
    }

    public function broadcastOn(): array
    {
        return [ new Channel('admin.print') ];
    }

    public function broadcastWith(): array
    {
        $orderPayload = [];
        if ($this->deviceOrder) {
            $orderPayload = $this->deviceOrder->only([
                'id',
                'order_id',
                'order_number',
                'device_id',
                'status',
                'created_at',
                'guest_count',
            ]);
        }

        // Ensure items are trimmed down to name/quantity only
        $items = collect($this->items ?? [])->map(fn($it) => [
            'name' => $it['name'] ?? ($it->name ?? null),
            'quantity' => $it['quantity'] ?? ($it->quantity ?? null),
        ])->values()->all();

        return [
            'order' => $orderPayload,
            'tablename' => $this->deviceOrder?->table->name ?? null,
            'items' => $items,
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.printed';
    }
}
