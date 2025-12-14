<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class SessionReset implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public int $sessionId;
    public int $version;

    public function __construct(int $sessionId, int $version = 1)
    {
        $this->sessionId = $sessionId;
        $this->version = $version;
    }

    public function broadcastOn()
    {
        return new Channel('session.' . $this->sessionId);
    }

    public function broadcastAs()
    {
        return 'session.reset';
    }

    public function broadcastWith()
    {
        return [
            'session_id' => $this->sessionId,
            'version' => $this->version,
        ];
    }
}
