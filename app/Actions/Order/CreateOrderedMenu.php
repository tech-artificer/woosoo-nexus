<?php

namespace App\Actions\Order;

use App\Exceptions\MenuItemUnavailableException;
use App\Models\DeviceOrderItems;
use App\Models\Krypton\Menu;
use App\Models\Krypton\OrderedMenu;
use App\Models\Package;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateOrderedMenu
{
    use AsAction;

    public function handle(array $attr)
    {
        $menuItems = $attr['items'] ?? [];
        $expandedItems = [];

        foreach ($menuItems as $item) {
            $packageMenuId = (int) ($item['menu_id'] ?? 0);
            $isPackage = ! empty($item['is_package']);


            $expandedItems[] = array_merge($item, [
                'ordered_menu_id' => $item['ordered_menu_id'] ?? $packageMenuId,
            ]);

            // Tablet workflow contract:
            // - Initial order sends the selected package as one top-level item.
            // - Customer-selected meats are nested as package modifiers.
            // - Only submitted modifiers are stored; available-but-unselected
            //   package options such as P1/P2/B1/etc. must not be inserted.
            // Refill requests arrive as flat items and do not include a package.
            if (! empty($item['modifiers']) && is_array($item['modifiers'])) {
                foreach ($item['modifiers'] as $modifier) {
                    if (empty($modifier['menu_id'])) {
                        continue;
                    }

                    $expandedItems[] = [
                        'menu_id' => $modifier['menu_id'],
                        'ordered_menu_id' => $packageMenuId,
                        'quantity' => intval($modifier['quantity'] ?? 1),
                        'seat_number' => $item['seat_number'] ?? 1,
                        'note' => $item['note'] ?? 'Package modifier',
                    ];
                }
            }
        }

        $allMenuIds = collect($expandedItems)
            ->pluck('menu_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        try {
            $menuModels = Menu::whereIn('id', $allMenuIds)->get()->keyBy('id');
        } catch (\Throwable $e) {
            report($e);
            throw new \RuntimeException('Unable to validate Krypton menu items — POS connection failure.', 0, $e);
        }

        $missingIds = array_diff($allMenuIds, $menuModels->keys()->map(fn ($id) => (int) $id)->all());
        if (! empty($missingIds)) {
            throw MenuItemUnavailableException::forMissingIds($missingIds);
        }

        $created = [];

        foreach ($expandedItems as $key => $item) {
            $menuId = (int) $item['menu_id'];
            $menuModel = $menuModels->get($menuId);
            $quantity = intval($item['quantity'] ?? 1);
            $seatNumber = $item['seat_number'] ?? 1;
            $index = $item['index'] ?? ($key + 1);
            $note = $item['note'] ?? '';

            // Packages, meats/modifiers, sides, and add-ons are all real rows
            // in krypton_woosoo.menus. Always resolve the submitted menu_id
            // through POS so ordered_menus.menu_id, price, and display names
            // remain valid Krypton data instead of client-provided stubs.
            $isPackageIndicator = (bool) ($item['is_package'] ?? false);

            $price = $menuModel->price;
            $priceLevelId = $this->getMenuPriceLevel($menuId);
            $orderId = $attr['order_id'];
            $orderCheckId = $attr['order_check_id'] ?? null;
            $employeeLogId = $attr['employee_log_id'] ?? 1;

            $unitPrice = (float) $price;
            $totalItemPrice = $unitPrice * $quantity;
            $taxRate = config('api.krypton.tax_rate', 0.10);
            $taxAmount = round($totalItemPrice * $taxRate, 2);
            $subTotal = round($totalItemPrice + $taxAmount, 2);
            $orderedMenu = $this->createOrderedMenu($menuModel, $quantity, $seatNumber, $index, $note, $unitPrice, $priceLevelId, $orderId, $orderCheckId, $employeeLogId, $isPackageIndicator);

            $local = null;
            // Allow callers to opt-out of creating local mirror rows.
            $mirrorLocal = $attr['mirror_local'] ?? true;
            if ($mirrorLocal && ! empty($attr['device_order_id'])) {
                $localPayload = [
                    'order_id' => $attr['device_order_id'],
                    // Store the package/menu id as ordered_menu_id (package_id),
                    // not the POS ordered_menus.id. Package modifiers inherit
                    // the top-level package id so local rows preserve hierarchy.
                    'ordered_menu_id' => $item['ordered_menu_id'] ?? $menuId,
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

    protected function createOrderedMenu(Menu $menu, $quantity, $seatNumber, $index, $note, $unitPrice, $priceLevelId, $orderId, $orderCheckId, $employeeLogId, bool $isPackageIndicator = false)
    {
        $menuId = (int) $menu->getKey();
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
        if (app()->runningUnitTests() || app()->environment('testing') || env('APP_ENV') === 'testing') {
            $fake = new \stdClass;
            $fake->id = $menuId;
            $fake->order_id = $orderId;
            $fake->order_check_id = $orderCheckId;
            $fake->price = $unitPrice;
            $fake->original_price = $unitPrice;
            $fake->sub_total = $subTotal;
            $fake->tax = $taxAmount;
            $fake->note = $note;
            $fake->name = 'Menu '.$menuId; // Ensure name is always present for broadcasts
            $fake->receipt_name = 'Menu '.$menuId;
            $fake->menu_id = $menuId;
            $fake->quantity = $quantity;
            $fake->seat_number = $seatNumber;
            $fake->index = $index;

            return $fake;
        }

        $orderedMenu = OrderedMenu::fromQuery('CALL create_ordered_menu('.$placeholders.')', $params)->first();

        if (! $orderedMenu) {
            throw new \RuntimeException('Failed to insert ordered menu: stored procedure returned empty result');
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
        if (empty($menuId)) {
            return 1;
        }

        // During tests we avoid calling the external POS database. Return
        // a sensible default price level instead of executing the stored
        // procedure which would attempt a network/DB connection.
        if (app()->runningUnitTests() || app()->environment('testing') || env('APP_ENV') === 'testing') {
            return 1;
        }

        try {
            return Menu::fromQuery('CALL get_menu_price_levels_by_menu(?)', [$menuId])->first()?->price_level_id ?? 1;
        } catch (\Throwable $e) {
            report($e);

            return 1;
        }
    }

}
