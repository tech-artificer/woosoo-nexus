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
            $price = $item['price'] ?? Menu::find($menuId)->price ?? 0.00;
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
            if (!empty($attr['device_order_id'])) {
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

            $created[] = [
                'pos' => (array) $orderedMenu,
                'local' => $local ? $local->toArray() : null,
            ];
        }

        return $created;
    }

    protected function createOrderedMenu($menuId, $quantity, $seatNumber, $index, $note, $unitPrice, $priceLevelId, $orderId, $orderCheckId, $employeeLogId)
    {
        $menu = Menu::findOrFail($menuId);
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
        $orderedMenu = OrderedMenu::fromQuery('CALL create_ordered_menu(' . $placeholders . ')', $params)->first();

        if (!$orderedMenu) {
            throw new \Exception('Failed to insert ordered menu.');
        }

        return $orderedMenu;
    }

    private function getMenuPriceLevel($menuId)
    {
        if (empty($menuId)) return 1;
        return Menu::fromQuery('CALL get_menu_price_levels_by_menu(?)', [$menuId])->first() ?? 1;
    }
}
