<?php
// scripts/create_test_order.php
// Bootstraps the Laravel app and creates a DeviceOrder with provided order_id and status.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DeviceOrder;
use Illuminate\Support\Str;

$orderId = $argv[1] ?? null;
$status = $argv[2] ?? 'completed';

if (! $orderId) {
    $orderId = rand(10000, 99999);
}

$order = DeviceOrder::create([
    'device_id' => 1,
    'table_id' => 1,
    'order_id' => (string) $orderId,
    'order_number' => 'ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . '-' . $orderId,
    'status' => $status,
    'items' => json_encode([]),
    'meta' => json_encode([]),
    'terminal_session_id' => 1,
    'session_id' => 1,
]);

// Status may be cast to an enum; normalize for printing
$statusVal = is_object($order->status) && property_exists($order->status, 'value') ? $order->status->value : (string) $order->status;
echo "Created device_order id={$order->id} order_id={$order->order_id} status={$statusVal}\n";
