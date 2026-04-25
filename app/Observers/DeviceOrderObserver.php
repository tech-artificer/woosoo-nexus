<?php

namespace App\Observers;

use App\Models\DeviceOrder;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderVoided;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        // Check if status was actually changed (use post-save change detection)
        if ($deviceOrder->wasChanged('status')) {
            $oldStatus = $deviceOrder->getOriginal('status');
            $newStatus = $deviceOrder->getAttribute('status');

            // getAttribute() returns an OrderStatus enum due to the model cast.
            // Backed enums cannot be interpolated directly — extract the scalar value.
            $oldStatusStr = $oldStatus instanceof \BackedEnum ? $oldStatus->value : (string) ($oldStatus ?? '');
            $newStatusStr = $newStatus instanceof \BackedEnum ? $newStatus->value : (string) ($newStatus ?? '');

            $timestamp = now()->toIso8601String();
            Log::info("[🔔 DeviceOrder Status Change] order_id={$deviceOrder->order_id} status={$oldStatusStr} → {$newStatusStr} at {$timestamp}");

            DB::afterCommit(function () use ($deviceOrder, $newStatusStr): void {
                // Broadcast OrderStatusUpdated for real-time PWA notification
                // only after persistence commits to avoid phantom/stale events.
                OrderStatusUpdated::dispatch($deviceOrder);

                // Fan-out terminal lifecycle events synchronously after commit.
                if ($newStatusStr === \App\Enums\OrderStatus::COMPLETED->value) {
                    OrderCompleted::dispatch($deviceOrder);
                }

                if ($newStatusStr === \App\Enums\OrderStatus::VOIDED->value) {
                    OrderVoided::dispatch($deviceOrder);
                }

                if ($newStatusStr === \App\Enums\OrderStatus::CANCELLED->value) {
                    OrderCancelled::dispatch($deviceOrder);
                }
            });
        }
    }
}
