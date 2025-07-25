<?php

namespace App\Listeners;

use App\Events\OrderUpdateLogCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RunOrderUpdateLogListener
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
     */
    public function handle(OrderUpdateLogCreated $event): void
    {
        //
    }
}
