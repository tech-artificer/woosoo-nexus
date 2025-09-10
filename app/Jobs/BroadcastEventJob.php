<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $event;

    public function __construct($event)
    {
        $this->event = $event;
    }

    public function handle()
    {
        broadcast($this->event);
    }

    public function failed(\Throwable $exception)
    {
        \Log::error("Broadcast job permanently failed", [
            'event' => get_class($this->event),
            'error' => $exception->getMessage(),
        ]);
    }
}
