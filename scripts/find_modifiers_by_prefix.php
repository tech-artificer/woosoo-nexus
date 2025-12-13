<?php

// Usage: php scripts/find_modifiers_by_prefix.php "Meat Order"
// Calls MenuRepository::getMenusByGroup($groupName), loads parent menus, extracts
// receipt_name prefixes (first letter), then finds modifier-only rows matching
// those prefixes (receipt_name LIKE 'P%') and prints grouped results.

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$kernel->bootstrap();

$groupName = $argv[1] ?? 'Meat Order';

try {
    $repo = $app->make(App\Repositories\Krypton\MenuRepository::class);
    $rows = $repo->getMenusByGroup($groupName);

    echo "Stored-proc rows count: " . count($rows) . "\n";

    $ids = collect($rows)->pluck('id')->filter()->unique()->values()->all();
    echo "Parent IDs: " . json_encode($ids) . "\n\n";

    if (empty($ids)) {
        echo "No parent IDs returned by stored-proc.\n";
        exit(0);
    }

    $parents = App\Models\Krypton\Menu::whereIn('id', $ids)->get(['id','name','receipt_name']);
    echo "Parent menu rows (with receipt_name):\n" . $parents->toJson(JSON_PRETTY_PRINT) . "\n\n";

    $prefixes = $parents->pluck('receipt_name')->filter()->map(function ($r) {
        return strtoupper(substr($r, 0, 1));
    })->unique()->values()->all();

    echo "Prefixes found: " . json_encode($prefixes) . "\n\n";

    $grouped = [];
    foreach ($prefixes as $prefix) {
        $mods = App\Models\Krypton\Menu::where('receipt_name', 'like', $prefix . '%')
            ->where('is_modifier_only', true)
            ->get(['id','name','receipt_name','menu_group_id']);

        $grouped[$prefix] = $mods->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->name,
                'receipt_name' => $m->receipt_name,
                'menu_group_id' => $m->menu_group_id,
            ];
        })->values()->all();
    }

    echo "Modifiers grouped by prefix:\n" . json_encode($grouped, JSON_PRETTY_PRINT) . "\n";

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
