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

class ProcessOrderLogs implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $logs = OrderUpdateLog::where('is_processed', false)
            ->whereNull('deleted_at')
            ->with('deviceOrder') // Eloquent relation
            ->get();

        Log::info("Processed & broadcasted Order ID {$logs}");
        foreach ($logs as $log) {
            try {
                $deviceOrder = $log->deviceOrder;

                if (!$deviceOrder) {
                    throw new \Exception("No device order for Order ID {$log->order_id}");
                }

                if( $log->is_open == false ) {
                    $deviceOrder->status = OrderStatus::COMPLETED;
                    $deviceOrder->save();

                    broadcast(new OrderCompleted($deviceOrder))->toOthers();

                    $log->update([
                        'is_processed' => true,
                        // 'deleted_at' => now(),
                    ]);
                }

                Log::info("Processed & broadcasted Order ID {$log->order_id}");

            } catch (\Throwable $e) {
                // Optional: create a retry count column
                // $log->increment('retry_count'); // If you add this field
                Log::error("Failed to process Order ID {$log->order_id}: {$e->getMessage()}");
            }
        }
    }
}
