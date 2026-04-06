<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

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
     * Backward-compatible entrypoint used by legacy callers.
     * Broadcast synchronously so order lifecycle updates do not depend on a queue worker.
     */
    public function dispatchBroadcastJob($event): void
    {
        $eventClass = get_class($event);
        $success = $this->broadcastWithRetry($event);

        $context = [
            'event' => class_basename($eventClass),
            'order_id' => $event->order?->id ?? $event->order_id ?? 'unknown',
            'device_id' => $event->order?->device_id ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s.u')
        ];

        if ($success) {
            Log::info("📤 [Broadcast] Event sent synchronously", $context);
            return;
        }

        Log::warning("⚠️ [Broadcast] Event failed after sync retries", $context);
    }

}