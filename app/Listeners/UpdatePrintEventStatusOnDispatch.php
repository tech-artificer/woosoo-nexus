<?php

namespace App\Listeners;

use App\Events\PrintOrder;
use App\Models\PrintEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdatePrintEventStatusOnDispatch implements ShouldQueue
{
    /**
     * Update the PrintEvent backend_status to 'broadcast' and set broadcast_at
     * when the PrintOrder event is dispatched.
     */
    public function handle(PrintOrder $event): void
    {
        $deviceOrder = $event->deviceOrder;
        if (! $deviceOrder) {
            return;
        }

        if (! config('nexus.print_events_enabled', false)) {
            Log::info('Skipping PrintEvent status update because woosoo-print-bridge is primary.', [
                'device_order_id' => $deviceOrder->id,
            ]);

            return;
        }

        // Find the latest print event for this device order
        $printEvent = PrintEvent::where('device_order_id', $deviceOrder->id)
            ->latest('id')
            ->first();

        if (! $printEvent) {
            Log::warning('No print event found for device order', [
                'device_order_id' => $deviceOrder->id,
            ]);

            return;
        }

        // Update backend_status to 'broadcast' and set broadcast_at if not already done
        if ($printEvent->backend_status === 'pending') {
            $printEvent->update([
                'backend_status' => 'broadcast',
                'broadcast_at' => now(),
            ]);
            Log::info('Print event marked as broadcast', [
                'print_event_id' => $printEvent->id,
                'device_order_id' => $deviceOrder->id,
            ]);
        }
    }
}
