<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Krypton\Order as KryptonOrder;
use App\Models\DeviceOrder;

$orderId = 19598;

echo "=== Checking Order ID: {$orderId} ===\n\n";

// Check Krypton (POS) database
echo "1. Krypton POS Database (krypton_woosoo.orders):\n";
try {
    $kryptonOrder = KryptonOrder::find($orderId);
    if ($kryptonOrder) {
        echo "   ✓ FOUND in krypton_woosoo database\n";
        echo "   ID: {$kryptonOrder->id}\n";
        echo "   Session ID: {$kryptonOrder->session_id}\n";
        echo "   Terminal Session ID: {$kryptonOrder->terminal_session_id}\n";
        echo "   Table ID: " . ($kryptonOrder->table_id ?? 'NULL') . "\n";
        echo "   Status: " . ($kryptonOrder->status ?? 'NULL') . "\n";
        echo "   Guest Count: {$kryptonOrder->guest_count}\n";
        echo "   Created: {$kryptonOrder->created_on}\n";
        echo "   Closed: {$kryptonOrder->closed_on}\n";
    } else {
        echo "   ✗ NOT FOUND in krypton_woosoo database\n";
    }
} catch (\Throwable $e) {
    echo "   ✗ Error querying Krypton: {$e->getMessage()}\n";
}

echo "\n2. Device Orders (woosoo_api.device_orders):\n";
try {
    $deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
    if ($deviceOrder) {
        echo "   ✓ FOUND in device_orders table\n";
        echo "   ID: {$deviceOrder->id}\n";
        echo "   Order ID: {$deviceOrder->order_id}\n";
        echo "   Device ID: {$deviceOrder->device_id}\n";
        echo "   Table ID: {$deviceOrder->table_id}\n";
        echo "   Status: {$deviceOrder->status->value}\n";
        echo "   Created: {$deviceOrder->created_at}\n";
        echo "   Items: " . $deviceOrder->items->count() . "\n";
    } else {
        echo "   ✗ NOT FOUND in device_orders table\n";
        echo "   (Order exists in Krypton but missing from device_orders tracking)\n";
    }
} catch (\Throwable $e) {
    echo "   ✗ Error querying device_orders: {$e->getMessage()}\n";
}

echo "\n3. Summary:\n";
$kryptonExists = KryptonOrder::find($orderId) !== null;
$deviceOrderExists = DeviceOrder::where('order_id', $orderId)->exists();

if ($kryptonExists && !$deviceOrderExists) {
    echo "   ⚠ Order exists in Krypton but NOT in device_orders\n";
    echo "   \n   CAUSE: Order was likely created on POS directly without device sync\n";
    echo "          or device_orders record was deleted/removed before completion.\n";
    echo "   \n   ACTION: To view this order in admin, manually create device_orders record:\n";
    $ko = KryptonOrder::find($orderId);
    echo "   \n   INSERT INTO device_orders (order_id, device_id, table_id, status, created_at, updated_at)\n";
    echo "   SELECT {$orderId}, NULL, {$ko->table_id}, 'completed', NOW(), NOW();\n";
} elseif (!$kryptonExists && $deviceOrderExists) {
    echo "   ✗ Order in device_orders but NOT in Krypton (orphaned)\n";
    echo "   ACTION: Check if order was deleted from Krypton\n";
} elseif ($kryptonExists && $deviceOrderExists) {
    echo "   ✓ Order properly synced between both databases\n";
} else {
    echo "   ✗ Order not found in either database\n";
}

echo "\n";
