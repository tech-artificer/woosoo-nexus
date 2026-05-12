<?php

namespace App\Events\Menu;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PackageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $deviceId,
        public readonly ?int $packageId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("device.{$this->deviceId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'package_id' => $this->packageId,
        ];
    }

    public function broadcastAs(): string
    {
        return 'package.updated';
    }
}
