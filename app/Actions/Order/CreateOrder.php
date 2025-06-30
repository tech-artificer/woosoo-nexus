<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Krypton\Order;
use App\Models\Krypton\Menu;
use App\Models\Device;

class CreateOrder
{
    use AsAction;

    public function handle(Device $device, array $params)
    {

        $orderId = $this->createOrder($params);

        return Order::findOrFail($orderId);
        // $transactionNo = $order->createTransactionNo($order->id, $order->session_id);
        // $order->update('transaction_number', $transactionNo);

        // $orderCheck = $this->createOrderCheck($order, $params);

        // $orderedMenus = $this->createOrderedMenus($order, $params);
        // // $deviceOrder = $device->deviceOrders()->create([
        // //     'table_id' => $device->table_id,
        // //     'order_id' => $order->id,


        // // ]);

        // // $orderCheck = $this->createOrderCheck($order, $params);
        // // $orderedMenus = $this->createOrderedMenus($order, $params);
        // return $deviceOrder;
        // // $orderCheck = $this->createOrderCheck($order, $params);

        // $deviceOrder = $device->deviceOrders()->create([
        //     'table_id' => $device->table_id,
        //     'items' => json_encode($params['items']),
        //     'meta' => json_encode([
        //         'total_amount' => $params['total_amount'],
        //         'guest_count' => $params['guest_count'],
        //         'note' => $params['note'],
        //     ]),
        // ]);


        // $tableOrder = new TableOrder();
        // $table = Table::find($request->user()->table_id);
        
        // $tableLink->order_id = $currentOrder->id;
        // $tableLink->table_id = $table->id;
        // $tableLink->primary_table_id =$table->id;
        // $tableLink->link_color = 1;
        // $tableLink->createLinkTable();

        // $tableOrder->order_id = $currentOrder->id;
        // $tableOrder->table_id = $table->id;
        // $tableOrder->parent_table_id = NULL;
        // $tableOrder->createTableOrder();
        
        // $table->changeTableStatus();

    }
    public function createOrder(array $params) : int {

       $order = Order::create([
            'session_id' => $params['session_id'],
            'terminal_session_id' => $params['terminal_session_id'],
            'date_time_opened' => now(),
            'date_time_closed' => NULL,
            'revenue_id' => $params['revenue_id'],
            'terminal_id' => $params['terminal_id'],
            'customer_id' => NULL,
            'is_open' => 1,
            'is_transferred' => 0,
            'is_voided' => '0',
            'guest_count' => $params['guest_count'],
            'service_type_id' => $params['service_type_id'],
            'start_employee_log_id' => $params['start_employee_log_id'], 
            'current_employee_log_id' => $params['current_employee_log_id'], 
            'close_employee_log_id' => $params['close_employee_log_id'], 
            'server_employee_log_id' => $params['server_employee_log_id'],
            'reference' => '',
            'cashier_employee_id' => $params['cashier_employee_id'], 
            'terminal_service_id' => $params['terminal_service_id'],
            'is_online_order' => 0,
        ]);

        return $order->id;

    }  
}
