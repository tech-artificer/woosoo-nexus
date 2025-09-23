<?php

namespace App\Listeners;

use App\Events\OrderUpdateLogCreated;
use App\Events\OrderCompleted;
use App\Models\DeviceOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Enums\OrderStatus;


class ProcessOrderUpdateListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderUpdateLogCreated  $event
     * @return void
     */
    public function handle(OrderUpdateLogCreated $event): void
    {
         try {
            // Start a database transaction
            DB::transaction(function () use ($event) {
                // Get the related DeviceOrder
                $deviceOrder = $event->orderUpdateLog->deviceOrder;

                if (!$deviceOrder) {
                    throw new Exception('DeviceOrder not found for order_id: ' . $event->orderUpdateLog->order_id);
                }

                // Update DeviceOrder status to completed
                $deviceOrder->update([
                    'status' => OrderStatus::COMPLETED,
                ]);

                // Log the action
                Log::info('DeviceOrder status updated to completed', [
                    'order_id' => $deviceOrder->order_id,
                    'status' => $deviceOrder->status,
                ]);

                // Broadcast the completion event to the device
                try {
                    event(new OrderCompleted($deviceOrder));
                } catch (Exception $e) {
                    Log::warning('Failed to broadcast OrderCompleted event', [
                        'order_id' => $deviceOrder->order_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } catch (Exception $e) {
            // Log the error
            Log::error('Failed to process OrderUpdateLogCreated event', [
                'order_id' => $event->orderUpdateLog->order_id,
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);

            // Optionally rethrow to trigger retries (if queued)
            // throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  OrderUpdateLogCreated  $event
     * @param  Exception  $exception
     * @return void
     */
    public function failed(OrderUpdateLogCreated $event, $exception)
    {
        Log::critical('ProcessOrderUpdate listener failed after retries', [
            'order_id' => $event->orderUpdateLog->order_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
