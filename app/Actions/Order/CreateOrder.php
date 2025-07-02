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
