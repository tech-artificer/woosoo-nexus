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
use App\Services\BroadcastService;
use App\Events\Order\OrderVoided;

use Illuminate\Support\Facades\Log;

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

        // Calculate totals from items if not provided or if they are 0
        // if (!isset($attributes['total_amount']) || $attributes['total_amount'] == 0) {
        //     Log::info('OrderService: Calculating totals from items', [
        //         'items_count' => count($attributes['items'] ?? []),
        //         'original_total' => $attributes['total_amount'] ?? 'not set'
        //     ]);
        //     // $calculatedTotals = $this->calculateTotalsFromItems($attributes['items'] ?? []);
        //     $attributes['subtotal'] = $calculatedTotals['subtotal'];
        //     $attributes['tax'] = $calculatedTotals['tax'];
        //     $attributes['total_amount'] += $calculatedTotals['total'];
        //     $attributes['discount_amount'] = $attributes['discount'] ?? 0;
        //     Log::info('OrderService: Totals calculated', $calculatedTotals);
        // }

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
                'is_available' => true,
                'is_locked' => true,
            ]);

            // Create an order check
            $orderCheck = CreateOrderCheck::run($this->attributes);
            // 
            $this->updateAttributes([
                'order_check_id' => $orderCheck->id,
            ]);

            // Create a new device order FIRST so we have device_order_id for items
            $deviceOrder = $device->orders()->create([
                'order_id' => $order->id,
                'table_id' => $device->table_id,
                'terminal_session_id' => $order->terminal_session_id,
                'status' => OrderStatus::CONFIRMED,
                'guest_count' => $order->guest_count,
                'session_id' => $order->session_id,
                'total' => $this->attributes['total_amount'],
                'subtotal' => $this->attributes['subtotal'],
                'tax' => $this->attributes['tax'],
                'discount' => $orderCheck->discount_amount,
            ]);

            // Add device_order_id so CreateOrderedMenu can save to device_order_items
            $this->updateAttributes([
                'device_order_id' => $deviceOrder->id,
            ]);

            // Create ordered menus (both POS and local device_order_items)
            $orderedMenus = CreateOrderedMenu::run($this->attributes);

            // Schedule creation of a PrintEvent after the database transaction commits.
            // This ensures we don't create print events while the order transaction is still open.
            DB::afterCommit(function () use ($deviceOrder) {
                try {
                    app(\App\Services\PrintEventService::class)->createForOrder($deviceOrder, 'INITIAL');
                } catch (\Throwable $e) {
                    report($e);
                }
            });

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

    public function voidOrder(DeviceOrder $deviceOrder) {
        app(BroadcastService::class)->dispatchBroadcastJob(new OrderVoided($deviceOrder));
    }

    protected function rollBackOrder(Device $device) {
        
    }

    /**
     * Calculate totals from items array
     * This matches the calculation logic in CreateOrderedMenu
     *
     * @param array $items
     * @return array
     */
    // protected function calculateTotalsFromItems(array $items): array
    // {
    //     $subtotal = 0;
    //     $tax = 0;
    //     $taxRate = 0.10; // 10% tax rate (same as CreateOrderedMenu)

    //     foreach ($items as $item) {
    //         $quantity = $item['quantity'] ?? 0;
    //         $price = $item['price'] ?? 0;
            
    //         // Calculate item total (price * quantity)
    //         $itemTotal = $price * $quantity;
            
    //         // Calculate tax for this item (same as CreateOrderedMenu: totalItemPrice * taxRate)
    //         $itemTax = $itemTotal * $taxRate;
            
    //         $subtotal += $itemTotal;
    //         $tax += $itemTax;
    //     }

    //     $total = $subtotal + $tax;

    //     return [
    //         'subtotal' => $subtotal,
    //         'tax' => $tax,
    //         'total' => $total,
    //     ];
    // }
}

// applied_taxes
// applied_discounts
// applied_menu_categories
