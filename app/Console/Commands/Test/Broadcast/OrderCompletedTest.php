<?php

namespace App\Console\Commands\Test\Broadcast;

use Illuminate\Console\Command;
use App\Services\BroadcastService;
use App\Events\Order\OrderCompleted;
use App\Enums\OrderStatus;
use App\Models\DeviceOrder;

class OrderCompletedTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcast:order-completed {order_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate broadcasting an Order Completed event to Reverb.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $order_id = $this->argument('order_id');
        $deviceOrder = DeviceOrder::where('order_id', $order_id)->first();
       
        $deviceOrder->update(['status' => OrderStatus::COMPLETED]);
        // broadcast(new OrderCreated($deviceOrder))->toOthers();
        app(BroadcastService::class)->dispatchBroadcastJob(new OrderCompleted($deviceOrder));
        $this->info('Order Created event broadcasted successfully!');
    }
}
