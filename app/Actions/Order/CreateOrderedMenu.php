<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Krypton\Menu;
use App\Models\Krypton\OrderedMenu;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateOrderedMenu
{
    use AsAction;

    public $orderedMenuId = null;
    public function handle(array $attr)
    {
        $this->orderedMenuId = null;

        $menuItems = $attr['items'] ?? [];

        $orderedMenus = [];

        foreach ($menuItems as $key => $item) {

            if( $key == 0 ) {
                $this->orderedMenuId = $item['menu_id'];
            }

            $item['order_id'] = $attr['order_id'];
            $item['order_check_id'] = $attr['order_check_id'];
            $item['employee_log_id'] = $attr['employee_log_id']; // Default to current user or 1

            $orderedMenus[] = $this->createOrderedMenu($item);
        }

        return $orderedMenus;
    }

    protected function createOrderedMenu(array $item = []) 
    {
        // This method should contain the logic to create an ordered menu item.
        // It should validate the input, check if the menu item exists, and then create the ordered menu item in the database.
        $menuId = $item['menu_id'];
        $quantity = $item['quantity'] ?? 0; // Default to 1 if not provided
        
        $menu = Menu::findOrFail($menuId);

        if( !$menu ) {
            throw new \Exception("Menu item not found.");
        }
      
        $now = Carbon::now();
        $employeeLogId = $item['employee_log_id'] ?? 1; // Default to current user or 1
        $orderId = $item['order_id'];
        $orderCheckId = $item['order_check_id'] ?? null; // Default to null if not provided

        if (!$orderId || !$orderCheckId) {
            throw new \Exception("Order ID and Order Check ID are required.");
        }
       
        // Basic calculations (you'll have more complex logic in a real POS)
        $price = $menu->price;
        $totalItemPrice = $price * $quantity;
        $taxRate = 0.10; // Example tax rate
        $taxAmount = $totalItemPrice * $taxRate;
        $subTotal = $totalItemPrice + $taxAmount; // Simple example

        $index = $item['index'] ?? 1; // Default to 1 if not provided
        $seatNumber = $item['seat_number'] ?? 1; // Default to 1 if not provided
        $note = $item['note'] ?? ''; // Default to empty string if not provided
        $priceLevelId = $this->getMenuPriceLevel($menuId); // Default to 1 if no price level found
        // Initialize many fields to 0.00 or false/null as defaults for a new item
        $zeroAmount = 0.00;
        $falseFlag = false;

        $params = [
            $orderId, $menuId, $priceLevelId, null, // pOrderedMenuId
            $orderCheckId, null, null, // pModifierAdjectiveId, pCancelledOrderId
            $seatNumber, $quantity, $price,
            $zeroAmount, // pDiscount
            $price, // pOriginalPrice
            $taxAmount, // pTax
            $now, // pTimeSent
            $index, // pIndex (increment this per item)
            $falseFlag, $falseFlag, // pIsPrinted, pIsHeld
            $menu->name, $menu->receipt_name ?? $menu->name, $menu->kitchen_name ?? $menu->name,
            $menu->description, $note, $employeeLogId,
            $menu->is_for_kitchen_display ?? true, // pIsForKitchenDisplay from menu config
            $totalItemPrice, // pTaxablePrice (example)
            $zeroAmount, // pItemDiscount
            $zeroAmount, // pCheckDiscount
            $price, // pUnitPrice
            $falseFlag, // pTaxExempt
            $falseFlag, // pIsTaxRemoved
            $totalItemPrice, // pNoTaxPrice
            $totalItemPrice, // pItemGrossTotal
            $totalItemPrice, // pItemOriginalWoVat (example)
            $zeroAmount, $zeroAmount, // pItemDiscountMain, pItemDiscountAdj
            $totalItemPrice, $totalItemPrice, $totalItemPrice, $totalItemPrice, // pVatable...
            $zeroAmount, $zeroAmount, $zeroAmount, $zeroAmount, // pVatExempt...
            $zeroAmount, $zeroAmount, // pNonVatable...
            $zeroAmount, $zeroAmount, // pZeroRated...
            $subTotal // pSubTotal
        ];

        try {

            $placeholdersArray = array_fill(0, count($params), '?');
            $placeholders = implode(', ', $placeholdersArray);

            // Call the procedure
            $orderedMenus = OrderedMenu::fromQuery('CALL create_ordered_menu(' . $placeholders . ')', $params)->first();

            if (empty($orderedMenus)) {
                throw new \Exception("Failed to add menu item.");
            }
            return $orderedMenus;
        } catch (\Throwable $th) {
            //throw $th;
        }

    }

    private function getMenuPriceLevel($menuId)
    {   
        if(empty($menuId)) return 1;
        // This method should return the price level for the given menu item.
        return Menu::fromQuery('CALL get_menu_price_levels_by_menu(?)', [$menuId])->first() ?? 1;
    }

}
