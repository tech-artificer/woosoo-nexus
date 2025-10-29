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
            'message' => 'Print order event triggered'
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
