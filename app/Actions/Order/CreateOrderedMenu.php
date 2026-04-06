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
        $expandedItems = [];

        foreach ($menuItems as $item) {
            $expandedItems[] = $item;

            if (!empty($item['modifiers']) && is_array($item['modifiers'])) {
                foreach ($item['modifiers'] as $modifier) {
                    if (empty($modifier['menu_id'])) {
                        continue;
                    }

                    $expandedItems[] = [
                        'menu_id' => $modifier['menu_id'],
                        'quantity' => intval($modifier['quantity'] ?? 1),
                        'seat_number' => $item['seat_number'] ?? 1,
                        'note' => $item['note'] ?? 'Package modifier',
                    ];
                }
            }
        }

        $created = [];

        foreach ($expandedItems as $key => $item) {
            if ($key == 0) {
                $this->orderedMenuId = $item['menu_id'];
            }

            $menuId = $item['menu_id'];
            $quantity = intval($item['quantity'] ?? 1);
            $seatNumber = $item['seat_number'] ?? 1;
            $index = $item['index'] ?? ($key + 1);
            $note = $item['note'] ?? '';
            
            // Package indicators are POS context markers (set meal parents), not regular menu items.
            // Prefer the is_package flag sent by the client (validated in StoreDeviceOrderRequest);
            // fall back to the legacy hardcoded ID list for backward compatibility.
            $isPackageIndicator = $item['is_package'] ?? in_array($menuId, [46, 47, 48, 49]);
            $menuModel = null;
            
            if (! app()->environment('testing')) {
                if (!$isPackageIndicator) {
                    try {
                        $menuModel = Menu::find($menuId);
                    } catch (\Throwable $e) {
                        report($e);
                        $menuModel = null;
                    }
                }
            }

            // For non-package items, always use the server-side DB price.
            // Fallback to client-supplied price only when DB lookup fails (null).
            // Package indicators have no menu record and must use client-supplied price.
            $price = (!$isPackageIndicator && $menuModel !== null)
                ? $menuModel->price
                : ($item['price'] ?? 0.00);
            $priceLevelId = $this->getMenuPriceLevel($menuId);
            $orderId = $attr['order_id'];
            $orderCheckId = $attr['order_check_id'] ?? null;
            $employeeLogId = $attr['employee_log_id'] ?? 1;

            $unitPrice = (float) $price;
            $totalItemPrice = $unitPrice * $quantity;
            $taxRate = config('api.krypton.tax_rate', 0.10);
            $taxAmount = round($totalItemPrice * $taxRate, 2);
            $subTotal = round($totalItemPrice + $taxAmount, 2);
            $orderedMenu = $this->createOrderedMenu($menuId, $quantity, $seatNumber, $index, $note, $unitPrice, $priceLevelId, $orderId, $orderCheckId, $employeeLogId, $isPackageIndicator);

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

    protected function createOrderedMenu($menuId, $quantity, $seatNumber, $index, $note, $unitPrice, $priceLevelId, $orderId, $orderCheckId, $employeeLogId, bool $isPackageIndicator = false)
    {
        // $isPackageIndicator is resolved by the caller from the validated request payload.
        // Fallback: the caller defaults to false, which is safe for regular menu items.
        
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
        $taxAmount = round($totalItemPrice * config('api.krypton.tax_rate', 0.10), 2);
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

        // The stored proc may not write order_check_id / original_price — set them explicitly.
        OrderedMenu::where('id', $orderedMenu->id)->update([
            'order_check_id' => $orderCheckId,
            'original_price' => $unitPrice,
        ]);

        return $orderedMenu->refresh();
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
