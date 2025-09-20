<?php

namespace App\Console\Commands\Test\Broadcast;

use Illuminate\Console\Command;
use App\Services\BroadcastService;
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
        $deviceOrder = DeviceOrder::find(10);
       

        // broadcast(new OrderCreated($deviceOrder))->toOthers();
        app(BroadcastService::class)->dispatchBroadcastJob(new OrderCreated($deviceOrder));
        $this->info('Order Created event broadcasted successfully!');
           
    }
}
