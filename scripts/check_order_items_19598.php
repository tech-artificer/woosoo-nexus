<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Krypton\OrderedMenu;
use App\Models\DeviceOrderItems;
use App\Models\DeviceOrder;

$orderId = 19598;

echo "=== Checking Order Items for Order ID: {$orderId} ===\n\n";

// Check Krypton ordered_menus
echo "1. Krypton ordered_menus:\n";
try {
    $kryptonItems = OrderedMenu::where('order_id', $orderId)->get();
    if ($kryptonItems->count() > 0) {
        echo "   ✓ FOUND {$kryptonItems->count()} items in Krypton\n";
        foreach ($kryptonItems as $item) {
            echo "   - Menu ID: {$item->menu_id}, Qty: {$item->quantity}, Price: {$item->price}\n";
        }
    } else {
        echo "   ✗ NO items found in Krypton\n";
    }
} catch (\Throwable $e) {
    echo "   ✗ Error: {$e->getMessage()}\n";
}

// Check device_order_items
echo "\n2. device_order_items:\n";
try {
    $deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
    if (!$deviceOrder) {
        echo "   ⚠ No device_orders record yet (need to create it first)\n";
        $deviceOrderItems = collect([]);
    } else {
        $deviceOrderItems = DeviceOrderItems::where('order_id', $deviceOrder->id)->get();
        if ($deviceOrderItems->count() > 0) {
            echo "   ✓ FOUND {$deviceOrderItems->count()} items\n";
            foreach ($deviceOrderItems as $item) {
                echo "   - Menu ID: {$item->menu_id}, Qty: {$item->quantity}, Price: {$item->price}\n";
            }
        } else {
            echo "   ✗ NO items found\n";
        }
    }
} catch (\Throwable $e) {
    echo "   ✗ Error: {$e->getMessage()}\n";
}

echo "\n3. Summary:\n";
$kryptonItemCount = OrderedMenu::where('order_id', $orderId)->count();
$deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
$deviceItemCount = $deviceOrder ? DeviceOrderItems::where('order_id', $deviceOrder->id)->count() : 0;

if ($kryptonItemCount > 0 && $deviceItemCount == 0) {
    echo "   ⚠ Items exist in Krypton but NOT in device_order_items\n";
    echo "   ACTION: Need to sync {$kryptonItemCount} items to device_order_items\n";
    
    if (!$deviceOrder) {
        echo "   \n   STEP 1: Create device_orders record first\n";
        echo "   INSERT INTO device_orders (order_id, device_id, table_id, status, created_at, updated_at)\n";
        echo "   VALUES ({$orderId}, NULL, NULL, 'completed', '2025-12-18 21:04:54', NOW());\n";
    }
    
    echo "   \n   STEP 2: Insert items from Krypton to device_order_items\n";
    $items = OrderedMenu::where('order_id', $orderId)->get();
    foreach ($items as $item) {
        echo "   -- Item: Menu {$item->menu_id}\n";
    }
} elseif ($kryptonItemCount > 0 && $deviceItemCount > 0) {
    echo "   ✓ Items properly synced ({$kryptonItemCount} items)\n";
} elseif ($kryptonItemCount == 0) {
    echo "   ⚠ No items in Krypton (order might be empty)\n";
}

echo "\n";
