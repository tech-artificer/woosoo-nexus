<?php

declare(strict_types=1);

use App\Broadcasting\OrderBroadcaster;
use App\Enums\OrderStatus;
use App\Http\Controllers\Admin\KdsController;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Throwable;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Skip Reverb; POS table eager-load may still throw after a successful DB commit.
app()->instance(OrderBroadcaster::class, new class
{
    public function statusChanged(DeviceOrder $order): void {}

    public function itemToggled(DeviceOrderItems $item): void {}
});

$admin = User::query()->where('is_admin', true)->first();
if (! $admin) {
    echo "NO_ADMIN\n";
    exit(1);
}

function kdsAdvance(object $controller, User $admin, DeviceOrder $order, string $label): void
{
    auth()->login($admin);
    $before = $order->fresh()->status->value;
    $http = '?';
    $body = '';

    try {
        $response = $controller->advance($order);
        $http = (string) $response->getStatusCode();
        $body = $response->getContent();
    } catch (Throwable $e) {
        $http = 'EX';
        $body = preg_replace('/\s+/', ' ', $e->getMessage()) ?? $e->getMessage();
    }

    $after = $order->fresh()->status->value;
    echo "{$label}_HTTP={$http}\n";
    echo "{$label}_BODY={$body}\n";
    echo "{$label}_DB={$before}->{$after}\n";
}

echo 'ADMIN_ID='.$admin->id."\n";

$controller = app(KdsController::class);

$template = DeviceOrder::query()->whereNotNull('device_id')->orderByDesc('id')->first();
$tableId = $template?->table_id ?? 1;
$branchId = $template?->branch_id ?? 1;
$terminalSessionId = $template?->terminal_session_id ?? 1;
$sessionId = $template?->session_id ?? 1;

$device = Device::query()->where('name', 'KDS Verify Tablet')->first();

if (! $device) {
    $device = Device::query()->create([
        'branch_id' => $branchId,
        'name' => 'KDS Verify Tablet',
        'table_id' => $tableId,
        'is_active' => true,
        'status' => 'online',
        'type' => 'tablet',
        'security_code' => '123456',
        'security_code_lookup' => hash('sha256', '123456'),
    ]);
    echo "CREATED_DEVICE={$device->id}\n";
} else {
    echo "REUSED_DEVICE={$device->id}\n";
}

$orderDefaults = [
    'branch_id' => $branchId,
    'device_id' => $device->id,
    'table_id' => $tableId,
    'terminal_session_id' => $terminalSessionId,
    'session_id' => $sessionId,
    'subtotal' => 50,
    'tax' => 6,
    'total' => 56,
    'discount' => 0,
    'guest_count' => 1,
];

$inProgress = DeviceOrder::query()->create(array_merge($orderDefaults, [
    'order_id' => random_int(90000, 99999),
    'order_number' => 'KDS-VERIFY-'.random_int(1000, 9999),
    'status' => 'in_progress',
    'subtotal' => 100,
    'tax' => 12,
    'total' => 112,
    'guest_count' => 2,
]));

$item = DeviceOrderItems::query()->create([
    'order_id' => $inProgress->id,
    'menu_id' => 1,
    'name' => 'Verify Item',
    'quantity' => 1,
    'price' => 100,
    'subtotal' => 100,
    'done' => false,
    'index' => 0,
]);

echo "SEEDED_IN_PROGRESS={$inProgress->id} ITEM={$item->id}\n";
kdsAdvance($controller, $admin, $inProgress, 'GATE');

$item->update(['done' => true, 'done_at' => now()]);
kdsAdvance($controller, $admin, $inProgress, 'HAPPY');

$confirmed = DeviceOrder::query()->create(array_merge($orderDefaults, [
    'order_id' => random_int(90000, 99999),
    'order_number' => 'KDS-VERIFY-C-'.random_int(1000, 9999),
    'status' => 'confirmed',
]));
kdsAdvance($controller, $admin, $confirmed, 'CONFIRMED');

$ready = DeviceOrder::query()->create(array_merge($orderDefaults, [
    'order_id' => random_int(90000, 99999),
    'order_number' => 'KDS-VERIFY-R-'.random_int(1000, 9999),
    'status' => 'ready',
]));
kdsAdvance($controller, $admin, $ready, 'READY');

$served = DeviceOrder::query()->create(array_merge($orderDefaults, [
    'order_id' => random_int(90000, 99999),
    'order_number' => 'KDS-VERIFY-S-'.random_int(1000, 9999),
    'status' => 'served',
]));
kdsAdvance($controller, $admin, $served, 'SERVED');

$method = new ReflectionMethod($controller, 'toKdsState');
$method->setAccessible(true);
echo 'READY_KDS_STATE='.$method->invoke($controller, OrderStatus::READY)."\n";
