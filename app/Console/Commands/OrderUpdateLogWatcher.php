<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OrderUpdateLogWatcher extends Command
{
    // /**
    //  * The name and signature of the console command.
    //  *
    //  * @var string
    //  */
    // protected $signature = 'app:order-update-log-watcher';

    // /**
    //  * The console command description.
    //  *
    //  * @var string
    //  */
    // protected $description = 'Command description';

    protected $signature = 'orders:watch-update-logs';
    protected $description = 'Watch OrderUpdateLogs table and process new entries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }

    // protected function processLog(OrderUpdateLog $log)
    // {
    //     try {
    //         // Example: Update related Order status
    //         $order = $log->order; // Make sure you have a relation or fetch it manually.
    //         if ($order) {
    //             $order->status = 'paid'; // or whatever logic you need
    //             $order->save();
    //         }

    //         // Broadcasting to devices (Reverb)
    //         broadcast(new \App\Events\OrderUpdated($order));

    //         // Mark log as processed
    //         $log->update(['is_processed' => true]);

    //         $this->info("Processed Log ID: {$log->id}");

    //     } catch (\Throwable $e) {
    //         Log::error("Failed to process log ID {$log->id}: " . $e->getMessage());
    //     }
    // }
}
