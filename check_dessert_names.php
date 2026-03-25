<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// First, check what groups exist
echo "=== All Groups ===\n";
$groups = \App\Models\Krypton\MenuGroup::all();
foreach ($groups as $g) {
  echo "ID: {$g->id} | name: {$g->name}\n";
}

// Try stored procedure
echo "\n=== Via getMenusByGroup('dessert') Procedure ===\n";
$repo = new \App\Repositories\Krypton\MenuRepository();
$desserts = $repo->getMenusByGroup('dessert');
if ($desserts->isNotEmpty()) {
  foreach ($desserts->take(5) as $m) {
    echo "ID: {$m->id} | name: " . ($m->name ?? 'NULL') . " | receipt_name: " . ($m->receipt_name ?? 'NULL') . "\n";
  }
} else {
  echo "No results from getMenusByGroup\n";
}

// Try category fetch
echo "\n=== Via getMenusByCategory('dessert') Procedure ===\n";
$desserts2 = $repo->getMenusByCategory('dessert');
if ($desserts2->isNotEmpty()) {
  foreach ($desserts2->take(5) as $m) {
    echo "ID: {$m->id} | name: " . ($m->name ?? 'NULL') . " | receipt_name: " . ($m->receipt_name ?? 'NULL') . "\n";
  }
} else {
  echo "No results from getMenusByCategory\n";
}

