<?php

namespace App\Console\Commands\Test\Broadcast;

use Illuminate\Console\Command;
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
    protected $signature = 'broadcast:order-completed {order_id=19624}';

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
        $deviceOrder = new DeviceOrder;
        // $deviceOrder->id = $this->argument('id');
        $deviceOrder->order_id = $this->argument('order_id'); 
        // $deviceOrder->order_number = $this->argument('order_number'); 
        // $deviceOrder->device_id = $this->argument('device_id');
        $deviceOrder->status = OrderStatus::COMPLETED;

        broadcast(new OrderCompleted($deviceOrder))->toOthers();
        $this->info('Order Completed event broadcasted successfully!');
           
    }
}
