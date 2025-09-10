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
    Table,
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
use App\Services\Krypton\KryptonContextService;

class OrderService
{
    public $attributes = [];
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
        $attributes = array_merge($defaults, $attributes, ['device_id' => $device->id, 'table_id' => $device->table_id]);

        $this->attributes = $attributes;

        return DB::transaction(function () use ($device) {
            // Create a new order using the provided attributes

            $order = CreateOrder::run($this->attributes);

            if (!$order) return;

            $this->updateAttributes([
                'order_id' => $order->id,
            ]);
            // Create a table order
            $tableOrder = CreateTableOrder::run($this->attributes);
            // Update table availability
            $table = Table::where('id', $this->attributes['table_id'])->update([
                'is_available' => false
            ]);

            // Create an order check
            $orderCheck = CreateOrderCheck::run($this->attributes);
            // 
            $this->updateAttributes([
                'order_check_id' => $orderCheck->id,
            ]);
            // Create ordered menus
            $orderedMenus = CreateOrderedMenu::run($this->attributes);

            // Create a new device order and associate it with the device
            $deviceOrder = $device->orders()->create([
                'order_id' => $order->id,
                'table_id' => $device->table_id,
                'terminal_session_id' => $order->terminal_session_id,
                'status' => OrderStatus::CONFIRMED,
                'session_id' => $order->session_id,
                'items' => $orderedMenus,
                'meta' => [
                    'order_check' => $orderCheck,
                    'table_order' => $tableOrder,
                ],
                'total' => $orderCheck->total_amount,
                'subtotal' => $orderCheck->subtotal_amount,
                'tax' => $orderCheck->tax_amount,
                'discount' => $orderCheck->discount_amount,
            ]);

            return $deviceOrder;
        });
    }

    protected function updateAttributes($array = []) {
        foreach ($array as $key => $value) {
            $this->attributes[$key] = $value;
        }
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

    protected function cancelOrder(Device $device) {
        
    }

    protected function voidOrder(Device $device) {
        
    }

    protected function rollBackOrder(Device $device) {
        
    }
}

// applied_taxes
// applied_discounts
// applied_menu_categories
