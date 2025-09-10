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
            return true;
        } catch (\Throwable $e) {
            Log::error("Broadcast failed", [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

/**
     * Broadcast with retry mechanism (sync retry).
     */
    public function broadcastWithRetry($event, int $maxRetries = 3, int $delayMs = 200): bool
    {
        $attempt = 0;

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
        BroadcastEventJob::dispatch($event)->onQueue('broadcasts');
    }

}