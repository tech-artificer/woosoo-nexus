<?php

// Debug helper: call get_menus_by_group('Meat Order'), pluck ids/group ids, then
// search menus table for modifiers (is_modifier_only = true) in those groups.
// Usage: php scripts/debug_group_modifiers.php "Meat Order"

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$kernel->bootstrap();

$groupName = $argv[1] ?? 'Meat Order';

try {
    $repo = $app->make(App\Repositories\Krypton\MenuRepository::class);
    $rows = $repo->getMenusByGroup($groupName);

    echo "Stored-proc rows (count=" . count($rows) . "):\n";
    echo json_encode($rows, JSON_PRETTY_PRINT) . "\n\n";

    $ids = collect($rows)->pluck('id')->unique()->values()->all();
    $groupIds = collect($rows)->pluck('menu_group_id')->unique()->values()->all();

    echo "Menu IDs: " . json_encode($ids) . "\n";
    echo "Menu group IDs: " . json_encode($groupIds) . "\n\n";

    if (! empty($groupIds)) {
        $modifiers = App\Models\Krypton\Menu::whereIn('menu_group_id', $groupIds)
            ->where('is_modifier_only', true)
            ->get(['id','name','receipt_name','menu_group_id','is_modifier_only']);

        echo "Modifiers in those groups (count=" . count($modifiers) . "):\n";
        echo $modifiers->toJson(JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No group IDs found.\n";
    }

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
