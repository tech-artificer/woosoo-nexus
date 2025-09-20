<?php

namespace App\Observers;

use App\Models\OrderUpdateLog;
use Illuminate\Support\Facades\Log;

class OrderUpdateLogObserver
{
    /**
     * Handle the OrderUpdateLog "created" event.
     */
    public function created(OrderUpdateLog $orderUpdateLog): void
    {

        Log::info("An OrderUpdate Log was created: {$orderUpdateLog}");
        // if (!$orderUpdateLog->is_processed) {
        //     ProcessOrderUpdateLog::dispatch($orderUpdateLog);
        // }
    }

    /**
     * Handle the OrderUpdateLog "updated" event.
     */
    public function updated(OrderUpdateLog $orderUpdateLog): void
    {
        // if (!$orderUpdateLog->is_processed) {
        //     ProcessOrderUpdateLog::dispatch($orderUpdateLog);
        // }
    }

    /**
     * Handle the OrderUpdateLog "deleted" event.
     */
    public function deleted(OrderUpdateLog $orderUpdateLog): void
    {
        //
    }

    /**
     * Handle the OrderUpdateLog "restored" event.
     */
    public function restored(OrderUpdateLog $orderUpdateLog): void
    {
        //
    }

    /**
     * Handle the OrderUpdateLog "force deleted" event.
     */
    public function forceDeleted(OrderUpdateLog $orderUpdateLog): void
    {
        //
    }
}
