<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Krypton\Order;
use App\Models\Krypton\Terminal;
use App\Models\Device;

$orderId = 19598;

$order = Order::find($orderId);
if (!$order) {
    echo "Order not found\n";
    exit(1);
}

echo "Order {$orderId} Details:\n";
echo "  Terminal Session ID: {$order->terminal_session_id}\n";
echo "  Session Number: {$order->session_number}\n";
echo "  Table ID: " . ($order->table_id ?? 'null') . "\n";
echo "  Created: {$order->created_on}\n\n";

// Try to find terminal
$terminal = Terminal::where('terminal_session', $order->terminal_session_id)->first();
if ($terminal) {
    echo "Terminal Info:\n";
    echo "  Terminal ID: {$terminal->id}\n";
    echo "  Terminal Name: {$terminal->terminal_name}\n";
    echo "  Terminal Session: {$terminal->terminal_session}\n\n";
    
    // Try to find device by terminal
    $device = Device::where('terminal_id', $terminal->id)->first();
    if ($device) {
        echo "✓ Found Device:\n";
        echo "  Device ID: {$device->id}\n";
        echo "  Device Name: {$device->name}\n";
        echo "  Terminal ID: {$device->terminal_id}\n";
    } else {
        echo "✗ No device mapped to this terminal\n";
        echo "\nAvailable devices:\n";
        foreach (Device::all() as $d) {
            echo "  ID: {$d->id} | Name: {$d->name} | Terminal ID: " . ($d->terminal_id ?? 'null') . "\n";
        }
    }
} else {
    echo "✗ Terminal not found for session {$order->terminal_session_id}\n";
}
