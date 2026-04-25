<?php
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create(
    '/sanctum/csrf-cookie',
    'GET',
    [], [], [],
    [
        'HTTP_HOST' => '192.168.100.7',
        'HTTPS' => 'on',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => '192.168.100.7',
        'HTTP_X_FORWARDED_PORT' => '443',
    ]
);
$response = $kernel->handle($request);
echo 'Status: ' . $response->getStatusCode() . PHP_EOL;
foreach ($response->headers->all() as $name => $values) {
    echo $name . ': ' . implode(', ', $values) . PHP_EOL;
}
