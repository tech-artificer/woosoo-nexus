<?php
// scripts/test_packages.php
// Boot Laravel and call Menu::getPackagesWithModifiers

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Bootstrap the console kernel so Eloquent etc. works
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Call without stored-proc rows so method uses local models
    $packages = \App\Models\Krypton\Menu::getPackagesWithModifiers();

    // Convert to array for JSON output
    $out = [];
    foreach ($packages as $pkg) {
        $modCount = isset($pkg['modifiers']) ? count($pkg['modifiers']) : 0;
        $sample = $pkg['modifiers'][0] ?? null;
        $out[] = [
            'package_id' => $pkg['id'] ?? ($pkg['ID'] ?? null),
            'package_name' => $pkg['name'] ?? null,
            'modifiers_count' => $modCount,
            'first_modifier' => $sample,
        ];
    }

    echo json_encode(['success' => true, 'packages' => $out], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ], JSON_PRETTY_PRINT);
}
