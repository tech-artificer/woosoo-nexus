<?php

// Debug helper to call MenuRepository::getMenusByGroup and print results
// Usage: php scripts/debug_group.php "Meat Order"

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Ensure facades work in this standalone script
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

// Bootstrap the framework (service providers, config, etc.)
$kernel->bootstrap();

$groupName = $argv[1] ?? 'Meat Order';

try {
    $repo = $app->make(App\Repositories\Krypton\MenuRepository::class);
    $rows = $repo->getMenusByGroup($groupName);

    echo "Stored-proc output for group '{$groupName}' (rows count: " . count($rows) . "):\n";
    echo json_encode($rows, JSON_PRETTY_PRINT) . "\n\n";

    $ids = collect($rows)->pluck('id')->unique()->values()->all();

    echo "Menu IDs returned: ";
    echo json_encode($ids) . "\n\n";

    if (! empty($ids)) {
        $menus = App\Models\Krypton\Menu::whereIn('id', $ids)->get(['id','name','receipt_name','is_modifier_only','menu_group_id']);
        echo "Corresponding Menu rows in POS DB:\n";
        echo $menus->toJson(JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No IDs returned by stored proc.\n";
    }

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
