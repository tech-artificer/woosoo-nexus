<?php

namespace App\Observers;

use App\Models\DeviceOrder;
use App\Events\Order\OrderStatusUpdated;
use Illuminate\Support\Facades\Log;

class DeviceOrderObserver
{
    /**
     * Handle the DeviceOrder "updated" event.
     * 
     * Broadcasts OrderStatusUpdated when device_orders.status changes
     * (triggered by POS DB trigger after_payment_update or manual API update).
     */
    public function updated(DeviceOrder $deviceOrder): void
    {
        // Check if status was actually changed (dirty tracking)
        if ($deviceOrder->isDirty('status')) {
            $oldStatus = $deviceOrder->getOriginal('status');
            $newStatus = $deviceOrder->getAttribute('status');

            $timestamp = now()->toIso8601String();
            Log::info("[🔔 DeviceOrder Status Change] order_id={$deviceOrder->order_id} status={$oldStatus} → {$newStatus} at {$timestamp}");

            // Broadcast OrderStatusUpdated for real-time PWA notification
            // This ensures PWA receives update whether from polling or from status change trigger
            OrderStatusUpdated::dispatch($deviceOrder);
        }
    }
}
