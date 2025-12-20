<?php
require __DIR__ . '/../vendor/autoload.php';
putenv('APP_ENV=local');
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Device Orders Table Columns:\n";
try {
    $columns = Schema::getColumns('device_orders');
    foreach ($columns as $col) {
        echo sprintf("  %-25s %s nullable=%s default=%s\n",
            $col['name'],
            $col['type'],
            $col['nullable'] ? 'yes' : 'no',
            $col['default'] ?? 'NULL'
        );
    }
} catch (\Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
}
