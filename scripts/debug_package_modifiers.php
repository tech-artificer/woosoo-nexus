<?php

// Debug helper to output modifiers for a package id using Menu::getModifiers
// Usage: php scripts/debug_package_modifiers.php 48

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Ensure facades work in this standalone script
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

// Bootstrap the framework (service providers, config, etc.)
$kernel->bootstrap();

$packageId = (int) ($argv[1] ?? 48);

try {
    $mods = App\Models\Krypton\Menu::getModifiers($packageId);

    echo "Modifiers for package_id={$packageId} (count=" . count($mods) . "):\n\n";

    // Build a simple array representation (avoid Resource response helpers which
    // depend on full routing/url services in this standalone script).
    $out = collect($mods)->map(function ($m) {
        // If the modifier row has a receipt_name like 'P1' but its own name is
        // the code (e.g. 'P1'), try to find the parent menu with the same
        // receipt_name where `is_modifier_only = false` and use that name as
        // the friendly display name.
        $displayName = $m->name;
        if ($m->receipt_name && ($m->name === $m->receipt_name || preg_match('/^[A-Z]\d+$/', $m->name))) {
            $parent = App\Models\Krypton\Menu::where('receipt_name', $m->receipt_name)->where('is_modifier_only', false)->first();
            if ($parent && $parent->name) {
                $displayName = $parent->name;
            }
        }

        return [
            'id' => $m->id,
            'group' => $m->group->name ?? null,
            'groupName' => $m->groupName ?? ($m->group->name ?? null),
            'name' => $m->name,
            'display_name' => $displayName,
            'kitchen_name' => $m->kitchen_name ?? null,
            'receipt_name' => $m->receipt_name ?? null,
            'price' => (string) ($m->price ?? '0.00'),
            'is_modifier' => (bool) ($m->is_modifier ?? false),
            'is_modifier_only' => (bool) ($m->is_modifier_only ?? false),
            'img_path' => $m->image?->path ?? null,
        ];
    })->values()->all();

    echo json_encode($out, JSON_PRETTY_PRINT) . "\n";

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
