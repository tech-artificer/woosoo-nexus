<?php

namespace App\Events\Order;

use App\Helpers\OrderBroadcastPayload;
use App\Models\DeviceOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DeviceOrder $deviceOrder) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('device.' . ($this->deviceOrder->device_id ?? '')),
            new Channel('orders.' . ($this->deviceOrder->order_id ?? '')),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order' => OrderBroadcastPayload::make($this->deviceOrder),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.completed';
    }
}
