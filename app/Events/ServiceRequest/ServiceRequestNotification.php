<?php

namespace App\Events\ServiceRequest;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\ServiceRequest;

class ServiceRequestNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $serviceRequest;
    /**
     * Create a new event instance.
     */
    public function __construct(ServiceRequest $serviceRequest)
    {
        $this->serviceRequest = $serviceRequest;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('service-requests.' . $this->serviceRequest->order_id),
            //  new PrivateChannel('orders.' . $this->deviceOrder->device_id);
            new PrivateChannel('admin.service-requests'),
        ];
    }

    /**
     * Get the data to broadcast for the notification.
     *
     * @return ServiceRequest
     */
    public function broadcastWith()
    {   
       
        return [
            'service_request' => $this->serviceRequest
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'service-request.notification'; // Custom event name for frontend to listen to
    }
}
