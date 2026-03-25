<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use App\Jobs\BroadcastEventJob;

class BroadcastService
{
    /**
     * Safely broadcast an event.
     */
    public function safeBroadcast($event): bool
    {
        try {
            broadcast($event);
  
        } catch (\Throwable $e) {
            Log::error("Broadcast failed", [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

/**
     * Broadcast with retry mechanism (sync retry).
     */
    public function broadcastWithRetry($event, int $maxRetries = 3, int $delayMs = 200): bool
    {
        $attempt = 0;
        Log::info("Broadcast Retry:  with {$maxRetries} attempts and {$delayMs}ms delay");
        while ($attempt < $maxRetries) {
            $attempt++;
            if ($this->safeBroadcast($event)) {
                return true;
            }

            usleep($delayMs * 1000); // backoff delay
        }

        return false;
    }

    /**
     * Queue a broadcast event for async retry via jobs.
     */
    public function dispatchBroadcastJob($event): void
    {   
        $eventClass = get_class($event);
        $jobId = \Illuminate\Support\Str::uuid();
        
        $broadcastJob = BroadcastEventJob::dispatch($event);
        
        Log::info("📤 [Broadcast] Job dispatched", [
            'event' => class_basename($eventClass),
            'order_id' => $event->order?->id ?? $event->order_id ?? 'unknown',
            'device_id' => $event->order?->device_id ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s.u')
        ]);
    }

}