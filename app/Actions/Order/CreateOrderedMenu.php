<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;

use App\Models\Krypton\Order;
use App\Models\Krypton\Menu;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\OrderedMenu;
use App\Models\Krypton\Employee;
use App\Models\Krypton\Revenue;

use Carbon\Carbon;

class CreateOrderedMenu
{
    use AsAction;

    public function handle(Order $order, OrderCheck $orderCheck, Revenue $revenue, Employee $employee, array $menuItems) : void
    {
        $this->createOrderedMenu($order, $orderCheck, $revenue, $employee, $menuItems);
    }

     protected function createOrderedMenu(
            Order $order, 
            OrderCheck $orderCheck, 
            Revenue $revenue, 
            Employee $employee, 
            array $menuItems
        ) { 

        $currentTime = Carbon::now();
        
        $orderedMenuId = NULL;
        foreach($menuItems as $key => $menuItem) {
            
            $menu = Menu::findOrFail($menuItem['menu_id']);

            $index = $menuItem['index'] ?? null;

            if( !$index ) {
                $index = $key + 1;
            }

            if( $menuItem['ordered_menu_id']  ) {
                $orderedMenuId = OrderedMenu::where('order_id', $order->id)->max('id');
            }else{
                $orderedMenuId = $menuItem['ordered_menu_id'];
            }

            $orderedMenu = OrderedMenu::create([
                 'order_id' => $order->id,
                'menu_id' => $menu->id,
                'price_level_id' => $revenue->price_level_id,
                'ordered_menu_id' => $orderedMenuId,
                'order_check_id' => $orderCheck->id,
                // 'modifier_adjective_id' => 'modifier_adjective_id',
                // 'cancelled_order_id' => 'cancelled_order_id',
                // 'seat_number' => 'seat_number',
                'quantity' => $menuItem['quantity'],
                'price' => $menuItem['price'],
                'discount' => $menuItem['discount'],
                'original_price' => $menu->price,
                'tax' => $menuItem['tax'],
                'time_sent' => $currentTime->format('H:i:s'), 
                'index' => $menuItem['index'] ?? $index,
                'is_printed' => 1,
                'is_held' => 0,
                'name' => $menu->name,
                'kitchen_name' => $menu->kitchen_name,
                'receipt_name' => $menu->receipt_name,
                'menu_description' => $menu->description,
                'note' => $menuItem['notes'] ?? null,
                'employee_log_id' => $employee->id,
                'for_kitchen_display' => 1,
                'taxable_price' => 0.00,
                'item_discount' => 0.00,
                'check_discount' => 0.00,
                'unit_price' => $menu->price,
                'tax_exempt' => 0.00,
                'cost' => $menu->cost,
                'is_taxed_removed' => 0,
                'no_tax_price' => 0.00,
                'item_gross_total' => $menuItem['subtotal'],
                'item_original_wo_vat' => $menuItem['subtotal'],
                'item_discount_main' => 0.00,
                'item_discount_adj' => 0.00,
                'vatable_original' => $menuItem['subtotal'],
                'vatable_sales_discounted' => $menuItem['subtotal'],
                'vatable_amount' => $menuItem['subtotal'],
                'vatable_sub_total' => $menuItem['subtotal'],
                'vat_exempt_sales' => 0.00,
                'vat_exempt_sales_discounted' => 0.00,
                'vat_exempt_amount' => 0.00,
                'vat_exempt_sub_total' => 0.00,
                'non_vatable_exempt_sales' => 0.00,
                'non_vatable_sub_total' => 0.00,
                'zero_rated_value' => 0.00,
                'zero_rated_sub_total' => 0.00,
                'sub_total' => $menuItem['subtotal'],
            ]);
        
        }

    }

}
