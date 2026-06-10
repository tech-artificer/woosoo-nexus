<?php

declare(strict_types=1);

namespace App\Events\Kds;

use App\Models\DeviceOrderItems;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DeviceOrderItems $item) {}

    public function broadcastOn(): array
    {
        return [new Channel('admin.orders')];
    }

    public function broadcastAs(): string
    {
        return 'item.toggled';
    }

    public function broadcastWith(): array
    {
        return [
            'item_id' => $this->item->id,
            'order_id' => $this->item->order_id,
            'done' => (bool) $this->item->done,
            'done_at' => $this->item->done_at?->toIso8601String(),
        ];
    }
}
