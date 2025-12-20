<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Krypton\Order;
use App\Models\Krypton\OrderedMenu;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use Illuminate\Support\Facades\DB;

$orderId = 19598;

echo "=== Syncing Order {$orderId} to device_orders ===\n\n";

try {
    DB::beginTransaction();
    
    // Step 1: Check if Krypton order exists
    $kryptonOrder = Order::find($orderId);
    if (!$kryptonOrder) {
        echo "✗ Order {$orderId} not found in Krypton. Aborting.\n";
        exit(1);
    }
    
    echo "✓ Found order in Krypton (Created: {$kryptonOrder->created_on})\n";
    
    // Step 2: Create device_orders record if missing
    $deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
    if (!$deviceOrder) {
        echo "Creating device_orders record...\n";
        $deviceOrder = DeviceOrder::create([
            'order_id' => $orderId,
            'device_id' => 1, // Using first available device
            'table_id' => $kryptonOrder->table_id ?? 1, // Default table if null
            'branch_id' => 1, // Default branch
            'session_id' => 1, // Default session
            'status' => 'completed',
            'terminal_session_id' => $kryptonOrder->terminal_session_id,
            'guest_count' => $kryptonOrder->guest_count ?? 0,
            'created_at' => $kryptonOrder->created_on,
            'updated_at' => now(),
        ]);
        echo "✓ Created device_orders ID: {$deviceOrder->id}\n";
    } else {
        echo "✓ device_orders record already exists (ID: {$deviceOrder->id})\n";
    }
    
    // Step 3: Sync items from Krypton ordered_menus to device_order_items
    $kryptonItems = OrderedMenu::where('order_id', $orderId)->get();
    echo "\nFound {$kryptonItems->count()} items in Krypton\n";
    
    if ($kryptonItems->count() > 0) {
        $existingItemCount = DeviceOrderItems::where('order_id', $deviceOrder->id)->count();
        
        if ($existingItemCount > 0) {
            echo "⚠ {$existingItemCount} items already exist in device_order_items. Skipping.\n";
        } else {
            echo "Syncing items to device_order_items...\n";
            foreach ($kryptonItems as $item) {
                DeviceOrderItems::create([
                    'order_id' => $deviceOrder->id, // Local device_orders.id
                    'menu_id' => $item->menu_id,
                    'ordered_menu_id' => $item->id,
                    'quantity' => $item->quantity,
                    'price' => $item->price ?? 0,
                    'subtotal' => ($item->price ?? 0) * $item->quantity,
                    'tax' => 0,
                    'discount' => 0,
                    'total' => ($item->price ?? 0) * $item->quantity,
                    'status' => 'served',
                    'created_at' => $item->date_time_ordered,
                    'updated_at' => now(),
                ]);
                echo "  ✓ Synced menu {$item->menu_id} (qty: {$item->quantity})\n";
            }
            echo "✓ Synced {$kryptonItems->count()} items\n";
        }
    }
    
    DB::commit();
    
    echo "\n✅ SUCCESS: Order {$orderId} fully synced to device_orders\n";
    echo "   - device_orders.id: {$deviceOrder->id}\n";
    echo "   - Items synced: {$kryptonItems->count()}\n";
    echo "   - Order now visible in admin\n";
    
} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n✗ ERROR: {$e->getMessage()}\n";
    echo "   {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
