<?php

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckStaleRelayHeartbeats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Only query printer_relay devices that have ever sent a heartbeat.
        // whereNotNull prevents false positives for newly registered devices.
        $stale = Device::where('type', 'printer_relay')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($stale as $relay) {
            Log::warning('[Relay] Stale heartbeat detected', [
                'device_id' => $relay->id,
                'device_name' => $relay->name,
                'last_seen' => $relay->last_heartbeat_at?->toIso8601String(),
                'minutes_silent' => now()->diffInMinutes($relay->last_heartbeat_at),
            ]);
            // Future hook: broadcast admin alert event here
        }
    }
}
