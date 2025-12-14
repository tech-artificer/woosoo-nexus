<?php

namespace App\Services;

use App\Models\PrintEvent;
use App\Models\DeviceOrder;

class PrintEventService
{
    /**
     * Create a print event for a DeviceOrder.
     */
    public function createForOrder(DeviceOrder $deviceOrder, string $eventType, array $meta = []): PrintEvent
    {
        return PrintEvent::create([
            'device_order_id' => $deviceOrder->id,
            'event_type' => $eventType,
            'meta' => $meta,
        ]);
    }
}
