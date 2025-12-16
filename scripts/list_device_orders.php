<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DeviceOrder;
$rows = DeviceOrder::all();
$items = [];
foreach ($rows as $r) {
    $items[] = [
        'id' => $r->id,
        'order_id' => $r->order_id,
        'status' => $r->status,
        'device_id' => $r->device_id,
    ];
}
echo json_encode($items, JSON_PRETTY_PRINT) . "\n";
