<?php
// Quick script to check device order status
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$device = \App\Models\Device::where('id', 1)->first();
if (!$device) {
    echo "❌ Device not found\n";
    exit(1);
}

echo "Device: {$device->name} (ID: {$device->id})\n";
echo "Branch: {$device->branch_id}\n\n";

$allOrders = \App\Models\DeviceOrder::where('device_id', 1)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "=== Last 5 Orders ===\n";
foreach ($allOrders as $order) {
    $itemCount = $order->items->count();
    $printStatus = $order->is_printed ? '✓ Printed' : '⏳ Not Printed';
    $createdAgo = $order->created_at->diffForHumans();
    echo sprintf("  [%d] OrderID: %s | Status: %-10s | Items: %d | %s | Created: %s\n",
        $order->id,
        $order->order_id,
        $order->status->value,
        $itemCount,
        $printStatus,
        $createdAgo
    );
}

$activeOrders = \App\Models\DeviceOrder::where('device_id', 1)
    ->whereIn('status', ['pending', 'confirmed'])
    ->get();

echo "\n=== Active Orders (Blocking New Submissions) ===\n";
if (count($activeOrders) > 0) {
    foreach ($activeOrders as $order) {
        echo "  ⚠️  ID: {$order->id}, OrderID: {$order->order_id}, Status: {$order->status->value}\n";
    }
    echo "\n💡 To create new order, need to transition these to completed/cancelled/voided\n";
} else {
    echo "  ✓ No active orders - ready for new submission\n";
}

// Check for print events
echo "\n=== Print Events for Device ===\n";
$printEvents = \App\Models\PrintEvent::whereHas('deviceOrder', function($q) {
    $q->where('device_id', 1);
})->orderBy('created_at', 'desc')->limit(5)->get();

if (count($printEvents) > 0) {
    foreach ($printEvents as $evt) {
        $ackStatus = $evt->is_acknowledged ? '✓ Acked' : '⏳ Pending';
        echo "  [" . $evt->id . "] Type: {$evt->event_type} | {$ackStatus} | Created: " . $evt->created_at->diffForHumans() . "\n";
    }
} else {
    echo "  No print events yet\n";
}
