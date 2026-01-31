<?php

namespace App\Events;

use App\Models\DeviceOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintRefill implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?DeviceOrder $deviceOrder;

    public array $items;

    public function __construct(?DeviceOrder $deviceOrder = null, array $items = [])
    {
        $this->deviceOrder = $deviceOrder;
        $this->items = $items;
    }

    public function broadcastOn(): array
    {
        return [new Channel('admin.print')];
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
        $items = collect($this->items ?? [])->map(fn ($it) => [
            'name' => $it['name'] ?? ($it->name ?? null),
            'quantity' => $it['quantity'] ?? ($it->quantity ?? null),
        ])->values()->all();

        return [
            'print_event_id' => $this->deviceOrder?->printEvent?->id,
            'device_id' => $this->deviceOrder?->device_id,
            'order_id' => $this->deviceOrder?->order_id,
            'session_id' => $this->deviceOrder?->session_id,
            'print_type' => 'REFILL',
            'refill_number' => $this->deviceOrder?->refill_number,
            'tablename' => $this->deviceOrder?->table?->name,
            'created_at' => ($this->deviceOrder?->created_at instanceof \DateTimeInterface) ? $this->deviceOrder->created_at->format(DATE_ATOM) : null,
            'order' => $orderPayload,
            'items' => $items,
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.printed';
    }
}
