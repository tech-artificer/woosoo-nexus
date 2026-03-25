<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

    private static bool $missingTableLogged = false;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        Log::info("🔄 [ProcessOrderLogs] Job execution started at " . date('Y-m-d H:i:s.u'));

        if (!Schema::hasTable('order_update_logs')) {
            if (!self::$missingTableLogged) {
                Log::warning('[ProcessOrderLogs] Skipping job: table order_update_logs does not exist.');
                self::$missingTableLogged = true;
            }
            return;
        }

        self::$missingTableLogged = false;

        $newLogs = OrderUpdateLog::where(['is_processed' => false, 'is_open' => false])
            ->with(['deviceOrder']) // Eloquent relation
            ->get();

        
        if ($newLogs->isEmpty()) {
            Log::info('✅ [ProcessOrderLogs] No new order updates found.');
            return;
        }

        $logCount = $newLogs->count();
        Log::info("📦 [ProcessOrderLogs] Found {$logCount} unprocessed logs");

        if( $logCount ) {
            $processedCount = 0;
            $voidedCount = 0;
            $completedCount = 0;

            foreach ($newLogs as $log) {

                try {

                    // $deviceOrder = $log->deviceOrder;
                    if( !$log->deviceOrder ) {
                        Log::warn('⚠️ [ProcessOrderLogs] Log has no device order. log_id=' . $log->id);
                        continue;
                    }

                    $action = null;
                 
                    if( $log->is_voided == true ) {
                        $log->deviceOrder->update(['status' => OrderStatus::VOIDED]);
                        $action = 'void';
                        $voidedCount++;
                        Log::info("🔕 [ProcessOrderLogs] Order VOIDED. order_id={$log->deviceOrder->id} device_id={$log->deviceOrder->device_id}");
                   
                    }else{
                        $action = 'complete';
                        $log->deviceOrder->update(['status' => OrderStatus::COMPLETED]);
                        $completedCount++;
                        Log::info("✅ [ProcessOrderLogs] Order COMPLETED. order_id={$log->deviceOrder->id} device_id={$log->deviceOrder->device_id}");
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
                    $processedCount++;
                
                } catch (\Throwable $e) {
                    // Optional: create a retry count column
                    // $log->increment('retry_count'); // If you add this field
                    Log::error("❌ [ProcessOrderLogs] Failed to process order_id {$log->order_id}: {$e->getMessage()}");
                }
            }
            
            $durationMs = number_format((microtime(true) - $startTime) * 1000, 2);
            Log::info("⏱️ [ProcessOrderLogs] Completed. processed={$processedCount} completed={$completedCount} voided={$voidedCount} duration={$durationMs}ms");
        
        }
    }
}
