<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

use App\Models\OrderUpdateLog;
use App\Models\DeviceOrder;
use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderVoided;
use App\Services\BroadcastService;

class ProcessOrderLogs implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Checking for log entries");

        $newLogs = OrderUpdateLog::where(['is_processed' => false, 'is_open' => false])
            ->with(['deviceOrder']) // Eloquent relation
            ->get();

        
        if ($newLogs->isEmpty()) {
            Log::info('No new order updates found.');
            return;
        }


        if( $newLogs->count() ) {

            foreach ($newLogs as $log) {

                try {

                    // $deviceOrder = $log->deviceOrder;
                    if( !$log->deviceOrder ) {
                        Log::info('Order has no device order.');
                        continue;
                    }

                    $action = null;
                 
                    if( $log->is_voided == true ) {
                        $log->deviceOrder->status = OrderStatus::VOIDED;
                        $action = 'void';
                        Log::info("Order is voided and broadcasted {$log->deviceOrder}.");
                   
                    }else{
                        $action = 'complete';
                        $log->deviceOrder->status = OrderStatus::COMPLETED;
                        Log::info("Order is COMPLETED and broadcasted {$log->deviceOrder}.");
                    }

                    $log->is_processed = true;
                    $log->deviceOrder->save();
                     $log->save();
                    if(  $action == 'void' ) {
                        app(BroadcastService::class)->dispatchBroadcastJob(new OrderVoided($log->deviceOrder));
                    }else{
                       app(BroadcastService::class)->dispatchBroadcastJob(new OrderCompleted($log->deviceOrder));
                    
                    }
          
                    $log->delete(); 
                    
                
                    // if (!$deviceOrder) {
                    //     throw new \Exception("No device order for Order ID {$log->order_id}");
                    // }

                    // if( $log->is_open == false && $log->action == 'paid' ) {

                    //     if( $deviceOrder->status == OrderStatus::CONFIRMED ) {
                    //         $deviceOrder->status =  OrderStatus::COMPLETED;
                    //         // Log::info("is true {$deviceOrder->status}");
                    //         $deviceOrder->save();
                    //         $log->is_processed = true;
                    //     }
                       
                    //     $log->delete();

                    //     broadcast(new OrderCompleted($deviceOrder));
                    //     Log::info("Processed & broadcasted Completed Order ID {$log->order_id}");
                    //      Log::info("{$deviceOrder}");
                    // }

                } catch (\Throwable $e) {
                    // Optional: create a retry count column
                    // $log->increment('retry_count'); // If you add this field
                    Log::error("Failed to process Order ID {$log->order_id}: {$e->getMessage()}");
                }
            }
        
        }
    }
}
