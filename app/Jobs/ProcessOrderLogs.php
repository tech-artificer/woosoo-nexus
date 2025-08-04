<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

use App\Models\OrderUpdateLog;
use App\Models\DeviceOrder;
use App\Enums\OrderStatus ;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderVoided;

class ProcessOrderLogs implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Checking for log entries");
        $logs = OrderUpdateLog::where(['is_processed' => false])
            ->whereNotNull('date_time_closed')
            ->with(['deviceOrder']) // Eloquent relation
            ->get();

        Log::info("Has {$logs->count()} Log/s");

        if( !$logs || $logs->isEmpty()) {
            Log::info("Nothing to process");
            return;
        }


        if( $logs->count() ) {

            foreach ($logs as $log) {
                try {
                    $deviceOrder = $log->deviceOrder;

                    if (!$deviceOrder) {
                        throw new \Exception("No device order for Order ID {$log->order_id}");
                    }

                    if( $log->is_voided == true ) {

                        if(  $deviceOrder->status == OrderStatus::CONFIRMED ) {
                            $deviceOrder->status = OrderStatus::VOIDED;
                            $log->is_processed = true;
                        }
                        broadcast(new OrderVoided($deviceOrder));
                        $deviceOrder->save();
                        $log->delete();
                        Log::info("Processed & broadcasted Voided Order ID {$log->order_id}");
                        $deviceOrder->save();
                        $log->delete();
                        return;
                    }

                    if( $log->is_open == false && $log->action == 'paid' ) {

                        if(  $deviceOrder->status == OrderStatus::CONFIRMED ) {
                            $deviceOrder->status = OrderStatus::COMPLETED;
                            $log->is_processed = true;
                        }
                    
                        $deviceOrder->save();
                        $log->delete();

                        broadcast(new OrderCompleted($deviceOrder));
                        Log::info("Processed & broadcasted Completed Order ID {$log->order_id}");
                    }

                } catch (\Throwable $e) {
                    // Optional: create a retry count column
                    // $log->increment('retry_count'); // If you add this field
                    Log::error("Failed to process Order ID {$log->order_id}: {$e->getMessage()}");
                }
            }
        
        }
    }
}
