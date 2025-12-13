<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$routes = app('router')->getRoutes();
$targets = [
    'api/orders/unprinted',
    'api/orders/printed/bulk',
    'api/orders/{orderId}/printed',
    'api/printer/heartbeat',
];

foreach ($routes as $r) {
    $uri = $r->uri();
    if (in_array($uri, $targets, true)) {
        echo $uri . " -> ";
        try {
            $m = $r->gatherMiddleware();
        } catch (Throwable $e) {
            $m = $r->middleware();
        }
        echo implode(',', (array) $m) . PHP_EOL;
    }
}
