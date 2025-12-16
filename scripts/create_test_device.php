<?php
// scripts/create_test_device.php
// Bootstraps the Laravel app and creates a branch, a device registration code, a device, and prints a device token.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceRegistrationCode;
use Illuminate\Support\Str;

// Create branch if none exists
$branch = Branch::first();
if (! $branch) {
    $branch = Branch::create([
        'branch_uuid' => (string) Str::uuid(),
        'name' => 'Test Branch'
    ]);
    echo "Created branch id={$branch->id}\n";
} else {
    echo "Using existing branch id={$branch->id}\n";
}

// Create a device registration code
$codeValue = 'ABC123';
$codeRow = DeviceRegistrationCode::where('code', $codeValue)->first();
if (! $codeRow) {
    $codeRow = DeviceRegistrationCode::create([
        'code' => $codeValue
    ]);
    echo "Created registration code {$codeValue}\n";
} else {
    echo "Using existing registration code {$codeValue}\n";
}

// Create device
$deviceName = 'Test Device';
$device = Device::where('name', $deviceName)->first();
if (! $device) {
    $device = Device::create([
        'name' => $deviceName,
        'ip_address' => '127.0.0.1',
        'app_version' => 'dev',
        'is_active' => true
    ]);
    echo "Created device id={$device->id}\n";
} else {
    echo "Using existing device id={$device->id}\n";
}

// Associate registration code to device if not yet used
if (! $codeRow->used_by_device_id) {
    $codeRow->used_by_device_id = $device->id;
    $codeRow->used_at = now();
    $codeRow->save();
    echo "Linked code {$codeValue} to device id={$device->id}\n";
}

// Create API token (abilities + expiration)
$token = $device->createToken('device-auth', ['*'], now()->addDays(7))->plainTextToken;
echo "TOKEN:" . $token . "\n";

// Print device info as JSON for convenience
echo json_encode([
    'device_id' => $device->id,
    'device_name' => $device->name,
    'branch_id' => $branch->id,
    'registration_code' => $codeValue,
    'token' => $token
], JSON_PRETTY_PRINT) . "\n";
