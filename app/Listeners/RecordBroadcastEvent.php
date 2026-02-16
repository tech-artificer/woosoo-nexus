<?php

namespace App\Listeners;

use App\Models\BroadcastEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RecordBroadcastEvent implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Store broadcast event for replay functionality
     */
    public function handle(object $event): void
    {
        // Extract channel and event data from the event object
        if (method_exists($event, 'broadcastOn')) {
            $channels = $event->broadcastOn();
            $channel = $channels[0]?->name ?? 'unknown';
        } else {
            $channel = 'unknown';
        }

        $eventName = class_basename($event);

        // Convert event to array for payload
        $payload = method_exists($event, 'broadcastWith')
            ? $event->broadcastWith()
            : get_object_vars($event);

        BroadcastEvent::record($channel, $eventName, $payload);
    }
}
