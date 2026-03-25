<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DeviceOrder;
use App\Models\Krypton\Session;
use App\Models\Krypton\Order;

$orderId = 19630;

echo "=== VISIBILITY CHECK FOR ORDER {$orderId} ===\n\n";

$deviceOrder = DeviceOrder::where('order_id', $orderId)->first();
if (!$deviceOrder) {
    echo "device_orders: NOT FOUND\n";
    exit(1);
}

echo "device_orders.id={$deviceOrder->id}\n";
echo "device_orders.status={$deviceOrder->status->value}\n";
echo "device_orders.session_id={$deviceOrder->session_id}\n";

$latestSessionModel = Session::orderByDesc('id')->first();
$latestSessionId = $latestSessionModel?->id;

echo "latest_session_by_model={$latestSessionId}\n";

echo "in_active_scope=" . (DeviceOrder::where('order_id', $orderId)->activeOrder()->exists() ? 'YES' : 'NO') . "\n";
echo "in_active_scope_latest_session=" . (
    DeviceOrder::where('order_id', $orderId)
        ->where('session_id', $latestSessionId)
        ->activeOrder()
        ->exists() ? 'YES' : 'NO'
) . "\n";

try {
    $sp = Session::fromQuery('CALL get_latest_session_id()')->first();
    $spId = $sp?->id;
    echo "latest_session_by_sp={$spId}\n";
    echo "in_active_scope_sp_session=" . (
        DeviceOrder::where('order_id', $orderId)
            ->where('session_id', $spId)
            ->activeOrder()
            ->exists() ? 'YES' : 'NO'
    ) . "\n";
} catch (\Throwable $e) {
    echo "latest_session_by_sp=ERROR: {$e->getMessage()}\n";
}

$kOrder = Order::find($orderId);
echo "krypton.orders.session_id=" . ($kOrder?->session_id ?? 'NULL') . "\n";
echo "krypton.orders.is_open=" . (int)($kOrder?->is_open ?? 0) . "\n";
