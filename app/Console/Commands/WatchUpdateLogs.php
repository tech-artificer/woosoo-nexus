<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Krypton\Order;
use App\Models\OrderUpdateLog;
use App\Models\DeviceOrder;
use Carbon\Carbon;
use App\Events\Order\OrderCompleted;
use App\Enums\OrderStatus;

class WatchUpdateLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:watch-update-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
          $deviceOrders = DeviceOrder::select('order_id')->where(['status' => OrderStatus::CONFIRMED])
                    ->whereDate('updated_at', Carbon::today())
                    ->get();
            $this->info("Order Updates: {$deviceOrders}");
        // $orders = Order::whereNull('date_time_closed')->get();
        while (true) {
            try {
                $deviceOrders = DeviceOrder::select('order_id')->where(['status' => OrderStatus::CONFIRMED])
                    ->whereDate('updated_at', Carbon::today())
                    ->get();

                $counter = count($logs);
                $this->info("Order Updates: {$counter}");
       
                if( count($logs) > 0 ) {

                    foreach ($logs as $log) {
                        $haystack = $deviceOrders->toArray();
                        $this->info("Search:  {$log->order_id}");
                        if( in_array($log->order_id, $haystack) ) {
                            $this->info("Found: {$log->order_id}");
                            $this->processLog($log->order_id);
                            // $deviceOrder = DeviceOrder::where('order_id', $log->order_id)->get();
                            // $deviceOrder->update(['status' => OrderStatus::COMPLETED]);
                            // // broadcast(new OrderCompleted($deviceOrder));
                            // $this->info("Dispatch: {$deviceOrder}");
                        }

                    }
                //     // $this->info("{$logs->count()} checking for completed orders");
                //     foreach ($orders as $order) {
                //         // $this->info("Checking {$order->id}");
                //         foreach ($logs as $log) {

                //             $this->info("{$log->order_id} checking.. .");
                //             if( $log->order_id == $order->id ) {
                //                 $completedOrder = DeviceOrder::where('order_id', $order->id)->first();
                //                 broadcast(new OrderCompleted($completedOrder));
                //                 // $this->info("Broadcasting {$completedOrder->order_id}, status: {$completedOrder->status}");
                //                 break;
                //             }

                //         }

                //     }


                //     event(new OrderCompleted($order));
                }

                // Log::info("{$logs->count()} logs processed.");
            } catch (\Throwable $e) {
                Log::error("WatchLogs error: {$e->getMessage()}");
            }

            usleep(500000); // 0.5 seconds
        }
    }

    protected function processLog($orderId)
    {   

        $this->info("Process: {$orderId}");
        $deviceOrder = DeviceOrder::select('status')->where('order_id', $orderId)->first();
        $deviceOrder->status = OrderStatus::COMPLETED;
        $deviceOrder->save();
        // // Example: Start a session if a payment is detected
        // if ($log->table_name === 'payments') {
        //     $payment = DB::connection('pos_db')->table('payments')->find($log->row_id);

        //     if ($payment) {
        //         Log::info("🎯 Real-time payment detected: ID {$payment->id}");

        //         // ✅ Trigger session start or push update
        //         // Event::dispatch(new PaymentReceived($payment));
        //         // or
        broadcast(new OrderCompleted($deviceOrder));
        $this->info("Broadcast: {$deviceOrder}");
        //     }
        // }
    }
}
