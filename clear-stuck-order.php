<?php
// Clear stuck order to test new submission
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$order = \App\Models\DeviceOrder::where('id', 1)->first();
if (!$order) {
    echo "❌ Order not found\n";
    exit(1);
}

echo "Clearing stuck order...\n";
echo "  Current Status: {$order->status->value}\n";

// Update status to VOIDED
$order->status = \App\Enums\OrderStatus::VOIDED;
$order->save();

echo "  New Status: {$order->status->value}\n";
echo "✓ Order voided - device is now ready for new submission\n";
