<?php

namespace App\Jobs;

use App\Models\PrintEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryUnacknowledgedPrintEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find events broadcast more than 2 minutes ago with no ack and still retryable.
        // NOTE: retry_count = backend re-broadcast counter.
        //       attempts     = device-ack counter (distinct — do NOT conflate).
        $stale = PrintEvent::where('backend_status', 'broadcast')
            ->where('broadcast_at', '<', now()->subMinutes(2))
            ->where('retry_count', '<', 5)
            ->get();

        foreach ($stale as $event) {
            $event->increment('retry_count');

            if ($event->deviceOrder) {
                event(new \App\Events\PrintOrder($event->deviceOrder));
                // Reset broadcast_at to now() so the 2-minute cooldown window is
                // enforced between each successive retry attempt, not just the first.
                $event->update(['broadcast_at' => now()]);
                Log::info('[RetryPrint] Re-broadcast print event', [
                    'print_event_id' => $event->id,
                    'retry_count'    => $event->retry_count,
                    'device_order_id'=> $event->device_order_id,
                ]);
            }
        }

        // Dead-letter events that exceeded the retry ceiling AND have had at least
        // 2 minutes since last broadcast — prevents immediate dead-lettering of an
        // event on its 5th retry during the same job run.
        $deadLettered = PrintEvent::where('backend_status', 'broadcast')
            ->where('retry_count', '>=', 5)
            ->where('broadcast_at', '<', now()->subMinutes(2))
            ->update(['backend_status' => 'dead_letter']);

        if ($deadLettered > 0) {
            Log::warning('[RetryPrint] Events moved to dead_letter', ['count' => $deadLettered]);
        }
    }
}
