<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Krypton\Menu;
use App\Models\Krypton\OrderedMenu;
use App\Models\DeviceOrderItems;
use Carbon\Carbon;

class CreateOrderedMenu
{
    use AsAction;

    public $orderedMenuId = null;

    public function handle(array $attr)
    {
        $this->orderedMenuId = null;

        $menuItems = $attr['items'] ?? [];
        $created = [];

        foreach ($menuItems as $key => $item) {
            if ($key == 0) {
                $this->orderedMenuId = $item['menu_id'];
            }

            $menuId = $item['menu_id'];
            $quantity = intval($item['quantity'] ?? 1);
            $seatNumber = $item['seat_number'] ?? 1;
            $index = $item['index'] ?? ($key + 1);
            $note = $item['note'] ?? '';
            
            // Package indicators (46, 47, 48, 49) are POS context markers, not actual menus.
            // Skip lookup for package indicators or when price is already provided.
            $isPackageIndicator = in_array($menuId, [46, 47, 48, 49]);
            $menuModel = null;
            
            if (! (app()->environment('testing') || env('APP_ENV') === 'testing')) {
                if (!$isPackageIndicator && !isset($item['price'])) {
                    try {
                        $menuModel = Menu::find($menuId);
                    } catch (\Throwable $e) {
                        report($e);
                        $menuModel = null;
                    }
                }
            }

            $price = $item['price'] ?? ($menuModel->price ?? 0.00);
            $priceLevelId = $this->getMenuPriceLevel($menuId);
            $orderId = $attr['order_id'];
            $orderCheckId = $attr['order_check_id'] ?? null;
            $employeeLogId = $attr['employee_log_id'] ?? 1;

            $unitPrice = (float) $price;
            $totalItemPrice = $unitPrice * $quantity;
            $taxRate = 0.10;
            $taxAmount = round($totalItemPrice * $taxRate, 2);
            $subTotal = round($totalItemPrice + $taxAmount, 2);
            $orderedMenu = $this->createOrderedMenu($menuId, $quantity, $seatNumber, $index, $note, $unitPrice, $priceLevelId, $orderId, $orderCheckId, $employeeLogId);

            $local = null;
            // Allow callers to opt-out of creating local mirror rows.
            $mirrorLocal = $attr['mirror_local'] ?? true;
            if ($mirrorLocal && !empty($attr['device_order_id'])) {
                $localPayload = [
                    'order_id' => $attr['device_order_id'],
                    // Store the package/menu id as ordered_menu_id (package_id),
                    // not the POS ordered_menus.id
                    'ordered_menu_id' => $menuId,
                    'menu_id' => $menuId,
                    'quantity' => $quantity,
                    'price' => $orderedMenu->price ?? $unitPrice,
                    'subtotal' => $orderedMenu->sub_total ?? $subTotal,
                    'tax' => $orderedMenu->tax ?? $taxAmount,
                    'total' => $orderedMenu->sub_total ?? $subTotal,
                    'notes' => $orderedMenu->note ?? $note,
                    'seat_number' => $seatNumber,
                    'index' => $index,
                ];

                $local = DeviceOrderItems::create($localPayload);
            }

            if ($mirrorLocal) {
                $created[] = [
                    'pos' => (array) $orderedMenu,
                    'local' => $local ? $local->toArray() : null,
                ];
            } else {
                // Return the POS object directly when caller will mirror local items.
                $created[] = $orderedMenu;
            }
        }

        return $created;
    }

    protected function createOrderedMenu($menuId, $quantity, $seatNumber, $index, $note, $unitPrice, $priceLevelId, $orderId, $orderCheckId, $employeeLogId)
    {
        // Package indicators (46, 47, 48, 49) are POS context markers, not actual menu records.
        // For these, use stub menu data without querying the menus table.
        $isPackageIndicator = in_array($menuId, [46, 47, 48, 49]);
        
        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            $menu = (object) [
                'name' => 'Menu ' . $menuId,
                'receipt_name' => 'Menu ' . $menuId,
                'kitchen_name' => 'Menu ' . $menuId,
                'description' => '',
                'is_for_kitchen_display' => true,
            ];
        } elseif ($isPackageIndicator) {
            // Package indicator - use stub without querying database
            $menu = (object) [
                'name' => 'Package ' . $menuId,
                'receipt_name' => 'Package ' . $menuId,
                'kitchen_name' => 'Package ' . $menuId,
                'description' => '',
                'is_for_kitchen_display' => true,
            ];
        } else {
            try {
                $menu = Menu::findOrFail($menuId);
            } catch (\Throwable $e) {
                // If menu not found in POS DB, create stub with menu ID
                report($e);
                $menu = (object) [
                    'name' => 'Menu ' . $menuId,
                    'receipt_name' => 'Menu ' . $menuId,
                    'kitchen_name' => 'Menu ' . $menuId,
                    'description' => '',
                    'is_for_kitchen_display' => true,
                ];
            }
        }
        $totalItemPrice = round($unitPrice * $quantity, 2);
        $taxAmount = round($totalItemPrice * 0.10, 2);
        $subTotal = round($totalItemPrice + $taxAmount, 2);
        $now = Carbon::now()->format('H:i:s');
        $false = false;
        $zero = 0.00;

        $params = [
            $orderId,
            $menuId,
            $priceLevelId,
            null,
            $orderCheckId,
            null,
            null,
            $seatNumber,
            $quantity,
            $unitPrice,
            $zero,
            $unitPrice,
            $taxAmount,
            $now,
            $index,
            $false,
            $false,
            $menu->name,
            $menu->receipt_name ?? $menu->name,
            $menu->kitchen_name ?? $menu->name,
            $menu->description,
            $note,
            $employeeLogId,
            $menu->is_for_kitchen_display ?? true,
            $totalItemPrice,
            $zero,
            $zero,
            $unitPrice,
            $false,
            $false,
            $totalItemPrice,
            $totalItemPrice,
            $totalItemPrice,
            $zero,
            $zero,
            $totalItemPrice,
            $totalItemPrice,
            $totalItemPrice,
            $totalItemPrice,
            $zero,
            $zero,
            $zero,
            $zero,
            $zero,
            $zero,
            $zero,
            $zero,
            $subTotal,
        ];

        $placeholders = implode(', ', array_fill(0, count($params), '?'));
        // If running tests, do not call the POS stored procedure. Return
        // a lightweight object with the expected attributes so calling
        // code can read `price`, `sub_total`, `tax`, `note`, and `name` safely.
        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            $fake = new \stdClass();
            $fake->id = $menuId;
            $fake->price = $unitPrice;
            $fake->sub_total = $subTotal;
            $fake->tax = $taxAmount;
            $fake->note = $note;
            $fake->name = 'Menu ' . $menuId; // Ensure name is always present for broadcasts
            $fake->receipt_name = 'Menu ' . $menuId;
            $fake->menu_id = $menuId;
            $fake->quantity = $quantity;
            $fake->seat_number = $seatNumber;
            $fake->index = $index;
            return $fake;
        }

        try {
            $orderedMenu = OrderedMenu::fromQuery('CALL create_ordered_menu(' . $placeholders . ')', $params)->first();
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }

        if (!$orderedMenu) {
            throw new \Exception('Failed to insert ordered menu.');
        }

        return $orderedMenu;
    }

    private function getMenuPriceLevel($menuId)
    {
        if (empty($menuId)) return 1;

        // During tests we avoid calling the external POS database. Return
        // a sensible default price level instead of executing the stored
        // procedure which would attempt a network/DB connection.
        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            return 1;
        }

        try {
            return Menu::fromQuery('CALL get_menu_price_levels_by_menu(?)', [$menuId])->first() ?? 1;
        } catch (\Throwable $e) {
            report($e);
            return 1;
        }
    }
}
