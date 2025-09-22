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
    protected $signature = 'broadcast:order-completed {id=19624}';

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
        $deviceOrder = DeviceOrder::find(19);
       
        $deviceOrder->status = OrderStatus::COMPLETED;
        $deviceOrder->save();
        // broadcast(new OrderCreated($deviceOrder))->toOthers();
        app(BroadcastService::class)->dispatchBroadcastJob(new OrderCompleted($deviceOrder));
        $this->info('Order Created event broadcasted successfully!');
           
    }
}
