<?php

namespace App\Repositories\Krypton;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

use App\Models\Krypton\Order;
use App\Models\Krypton\Table;

use App\Models\DeviceOrder;
use App\Models\Krypton\TerminalSession;

class OrderRepository
{
    /**
     * Fetches all orders and their associated device/table data from different databases.
     *
     * @return Collection
     */

    public static function getAllOrdersWithDeviceData() : Collection
    {
        $terminalSession = TerminalSession::current()->latest('created_on')->first() ?? false;

        if( !$terminalSession ) return collect([]);

        $orders = Order::select([
                'id','session_id','terminal_session_id',
                'date_time_opened','date_time_closed','revenue_id',
                'terminal_id','is_open','is_transferred',
                'is_voided','guest_count','service_type_id', 'is_available',
                'transaction_no', 'terminal_service_id','reprint_count',
        ])
        ->where(['terminal_session_id' => $terminalSession->id])
        ->with(['orderCheck', 'orderedMenus'])
        ->latest('created_on')
        ->get();

        $deviceOrders = DeviceOrder::select(['order_id', 'order_number', 'status', 'device_id', 'table_id'])
                            ->with('device', 'table')
                            ->where(['terminal_session_id' => $terminalSession->id])
                            ->get()
                            ->keyBy('order_id');


        $mergedOrders = $orders->transform(function ($order) use ($deviceOrders) {
            
            $data = $deviceOrders->get($order->id) ?? null;

            $order->deviceOrder = $data ?? null;
            $order->device = $data->device ?? null;
            $order->table = $data->table ?? null;

            unset($order->deviceOrder->device);
            unset($order->deviceOrder->table);

            return $order;
        });

        return $mergedOrders;
    }

    
    
}