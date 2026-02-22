<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;
use App\Models\Krypton\TableOrder;
use App\Models\Krypton\OrderedMenu;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;

$orderId = 19630;

echo "=== ORDER INVESTIGATION: {$orderId} ===\n\n";

try {
    $order = Order::find($orderId);
    echo "[KRYPTON] orders: " . ($order ? "FOUND" : "NOT FOUND") . "\n";
    if ($order) {
        echo "  - id: {$order->id}\n";
        echo "  - session_id: {$order->session_id}\n";
        echo "  - terminal_session_id: " . ($order->terminal_session_id ?? 'NULL') . "\n";
        echo "  - is_open: " . (int)$order->is_open . "\n";
        echo "  - is_voided: " . (int)$order->is_voided . "\n";
        echo "  - created_on: " . ($order->created_on ?? 'NULL') . "\n";
    }

    $orderChecks = OrderCheck::where('order_id', $orderId)->get();
    echo "[KRYPTON] order_checks: {$orderChecks->count()} row(s)\n";
    foreach ($orderChecks as $idx => $oc) {
        echo "  - #" . ($idx + 1) . " id={$oc->id}, total={$oc->total_amount}, subtotal={$oc->subtotal_amount}, tax={$oc->tax_amount}\n";
    }

    $tableOrders = TableOrder::where('order_id', $orderId)->get();
    echo "[KRYPTON] table_orders: {$tableOrders->count()} row(s)\n";
    foreach ($tableOrders as $idx => $to) {
        echo "  - #" . ($idx + 1) . " id={$to->id}, table_id={$to->table_id}, parent_table_id=" . ($to->parent_table_id ?? 'NULL') . "\n";
    }

    $orderedMenus = OrderedMenu::where('order_id', $orderId)->get();
    echo "[KRYPTON] ordered_menus: {$orderedMenus->count()} row(s)\n";
    foreach ($orderedMenus->take(10) as $idx => $om) {
        echo "  - #" . ($idx + 1) . " id={$om->id}, menu_id={$om->menu_id}, qty={$om->quantity}, price={$om->price}\n";
    }

    $deviceOrders = DeviceOrder::where('order_id', $orderId)->get();
    echo "\n[LOCAL] device_orders (woosoo_nexus): {$deviceOrders->count()} row(s)\n";
    foreach ($deviceOrders as $idx => $do) {
        echo "  - #" . ($idx + 1) . " id={$do->id}, device_id={$do->device_id}, table_id={$do->table_id}, session_id={$do->session_id}, status={$do->status->value}\n";

        $localItemsCount = DeviceOrderItems::where('order_id', $do->id)->count();
        echo "    -> device_order_items linked by device_orders.id={$do->id}: {$localItemsCount}\n";
    }

    echo "\n=== SUMMARY ===\n";
    echo "orders: " . ($order ? "OK" : "MISSING") . "\n";
    echo "order_checks: " . ($orderChecks->count() > 0 ? "OK" : "MISSING") . "\n";
    echo "table_orders: " . ($tableOrders->count() > 0 ? "OK" : "MISSING") . "\n";
    echo "ordered_menus: " . ($orderedMenus->count() > 0 ? "OK" : "MISSING") . "\n";
    echo "device_orders: " . ($deviceOrders->count() > 0 ? "OK" : "MISSING") . "\n";

} catch (\Throwable $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
