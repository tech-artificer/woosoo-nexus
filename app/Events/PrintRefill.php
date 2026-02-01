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
        // Eager-load table relationship to prevent N+1 queries in broadcastWith()
        if ($deviceOrder) {
            $this->deviceOrder = $deviceOrder->loadMissing('table');
        } else {
            $this->deviceOrder = null;
        }
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
        $items = collect($this->items ?? [])->map(function ($it) {
            // Handle both objects (from POS) and arrays (from manual payloads)
            if (is_object($it)) {
                return [
                    'name' => $it->name ?? $it->receipt_name ?? null,
                    'quantity' => $it->quantity ?? 1,
                ];
            }
            return [
                'name' => $it['name'] ?? null,
                'quantity' => $it['quantity'] ?? 1,
            ];
        })->values()->all();

        return [
            'print_event_id' => $this->deviceOrder?->printEvent?->id,
            'device_id' => $this->deviceOrder?->device_id,
            'order_id' => $this->deviceOrder?->order_id,
            'session_id' => $this->deviceOrder?->session_id,
            'print_type' => 'REFILL',
            'refill_number' => $this->deviceOrder?->refill_number,
            'tablename' => $this->deviceOrder?->table?->name,
            'guest_count' => $this->deviceOrder?->guest_count,
            'order_number' => $this->deviceOrder?->order_number,
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
