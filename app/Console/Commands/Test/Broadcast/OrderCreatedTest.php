<?php

namespace App\Console\Commands\Test\Broadcast;

use Illuminate\Console\Command;
use App\Events\Order\OrderCreated;
use App\Enums\OrderStatus;
use App\Models\DeviceOrder;

class OrderCreatedTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'broadcast:order-created {id=33} {order_id=19585} {order_number=ORD-000001} {device_id=7}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate broadcasting an Order Created event to Reverb.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deviceOrder = new DeviceOrder;
        $deviceOrder->id = $this->argument('id');
        $deviceOrder->order_id = $this->argument('order_id'); 
        $deviceOrder->order_number = $this->argument('order_number'); 
        $deviceOrder->device_id = $this->argument('device_id');
        $deviceOrder->status = OrderStatus::CONFIRMED;

        broadcast(new OrderCreated($deviceOrder))->toOthers();
        $this->info('Order Created event broadcasted successfully!');
           
    }
}
