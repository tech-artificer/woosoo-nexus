<?php

namespace App\Services\Krypton;

use Illuminate\Support\Facades\DB;
use App\Models\Krypton\{
    Order,
    Menu,
    Session,
    Tax,
    EmployeeLog,
    Revenue,
    Terminal,
    TerminalSession,
    TerminalService,
    CashTraySession,
};
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Actions\Order\{
    CreateOrder,
    CreateOrderCheck,
    CreateTableOrder,
    CreateOrderedMenu
};

use App\Enums\OrderStatus;
use App\Repositories\Krypton\MenuRepository;
use App\Repositories\Krypton\OrderRepository;
use App\Services\Krypton\KryptonContextService;
use App\Events\Order\OrderCreated;

class OrderService
{
    /**
     * Process an order for a given device with specified attributes.
     *
     * @param Device $device
     * @param array $attributes
     * @return Order|bool
     */
    public function processOrder(Device $device, array $attributes)
    {
        // Fetch default values and merge them with provided attributes
        $defaults = $this->getDefaultAttributes();
        $attributes = array_merge($defaults, $attributes);
      
        return DB::transaction(function () use ($attributes, $device) {
            // Create a new order using the provided attributes
            $order = CreateOrder::run($attributes);
            // $attributes['order_id'] = $order->id;
            
            return $order;
            if (!$order) {
                return false;
            }

            // Update the order with terminal and cashier details
            $order->update([
                'end_terminal_id' => $order->terminal_id, 
                'cash_tray_session_id' => $attributes['cash_tray_session_id'],
                'cashier_employee_id' => $attributes['cashier_employee_id'],
            ]);


            $tableOrder = CreateTableOrder::run($attributes);

            // Set additional order details
            $orderCheck = CreateOrderCheck::run($attributes);
            $attributes['order_check_id'] = $order->orderCheck->id;
            $orderedMenus = CreateOrderedMenu::run($attributes);
            // Create a new device order and associate it with the device
            $deviceOrder = $device->orders()->create([
                'order_id' => $order->id,
                'table_id' => $device->table_id,
                'terminal_session_id' => $order->terminal_session_id,
                'status' => OrderStatus::CONFIRMED,
                'items' => [],
                'meta' => [
                ],
            ]);

            broadcast(new OrderCreated($deviceOrder));

            return $deviceOrder;
        });
    }

    /**
     * Fetch default values needed for order processing.
     *
     * @return array
     */
    protected function getDefaultAttributes(): array
    {   
        $contextService = new KryptonContextService();
        $defaults = $contextService->getData();
        
        $params =  [
            'start_employee_log_id' => $defaults['employee_log_id'],
            'current_employee_log_id' => $defaults['employee_log_id'],
            'close_employee_log_id' =>   $defaults['employee_log_id'],
            'server_employee_log_id' => null,
            'is_online_order' => false,
            'reference' => '',

        ];        
        
        return array_merge($defaults, $params); 
    }

    /**
     * Check if a table is open based on the table ID.
     *
     * @param int|null $tableId
     * @return bool
     */
    public function checkIfTableIsOpen($tableId = null)
    {
        // Logic to check if a table is open should be implemented here
    }
}

