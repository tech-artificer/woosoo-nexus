<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;

use App\Models\Krypton\TableOrder;
use App\Models\Krypton\Order;
use App\Models\Krypton\Table;
use App\Models\Device;

class CreateTableOrder
{
    use AsAction;

    public function handle(Device $device, Order $order) : void
    {
        TableOrder::create([
            'order_id' => $order->id,
            'table_id' => $device->table_id,
            'parent_table_id' => null,
            'is_cleared' => 0,
            'is_printed' => 0, 
        ]);
    }
}
