<?php
// Direct database update using PDO
$force = in_array('--force', $argv ?? [], true);

if (! $force) {
    fwrite(STDERR, "Refusing to reseed package_modifiers without --force.\n");
    fwrite(STDERR, "Usage: php scripts/update_package_modifiers.php --force\n");
    exit(1);
}

$env = parse_ini_file('.env');
$dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_DATABASE']}";
$pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Prepare data
$packages = [
    46 => [49, 50, 51, 52, 53],
    47 => [49, 50, 51, 52, 53, 54, 55, 56],
    48 => [49, 50, 51, 52, 53, 54, 55, 56, 61, 62, 63, 64, 65, 66],
];

try {
    $pdo->beginTransaction();
    $pdo->exec('TRUNCATE TABLE package_modifiers');

    $stmt = $pdo->prepare('INSERT INTO package_modifiers (package_id, menu_id, position) VALUES (?, ?, ?)');

    foreach ($packages as $package_id => $modifiers) {
        foreach ($modifiers as $position => $menu_id) {
            $stmt->execute([$package_id, $menu_id, $position + 1]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

echo "✅ Package 46: 5 modifiers added\n";
echo "✅ Package 47: 8 modifiers added\n";
echo "✅ Package 48: 14 modifiers added\n";

// Verify
$result = $pdo->query('SELECT COUNT(*) FROM package_modifiers')->fetch();
echo "\n📊 Total package_modifiers in DB: {$result[0]}\n";

// Show details
echo "\n📋 PACKAGE 46 (Classic Feast):\n";
$rows = $pdo->query("
    SELECT pm.position, pm.menu_id, m.kitchen_name 
    FROM package_modifiers pm 
    JOIN menus m ON pm.menu_id = m.id 
    WHERE pm.package_id = 46 
    ORDER BY pm.position
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "  Position {$row['position']}: ID {$row['menu_id']} → {$row['kitchen_name']}\n";
}

echo "\n📋 PACKAGE 47 (Noble Selection):\n";
$rows = $pdo->query("
    SELECT pm.position, pm.menu_id, m.kitchen_name 
    FROM package_modifiers pm 
    JOIN menus m ON pm.menu_id = m.id 
    WHERE pm.package_id = 47 
    ORDER BY pm.position
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "  Position {$row['position']}: ID {$row['menu_id']} → {$row['kitchen_name']}\n";
}

echo "\n📋 PACKAGE 48 (Royal Banquet):\n";
$rows = $pdo->query("
    SELECT pm.position, pm.menu_id, m.kitchen_name 
    FROM package_modifiers pm 
    JOIN menus m ON pm.menu_id = m.id 
    WHERE pm.package_id = 48 
    ORDER BY pm.position
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "  Position {$row['position']}: ID {$row['menu_id']} → {$row['kitchen_name']}\n";
}

echo "\n✅ Update complete!\n";
