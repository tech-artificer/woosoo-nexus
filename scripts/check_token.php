<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Laravel\Sanctum\PersonalAccessToken;

$token = $argv[1] ?? null;
if (! $token) {
    echo "Usage: php check_token.php <token>\n";
    exit(1);
}

$pat = PersonalAccessToken::findToken($token);
if (! $pat) {
    echo "Token not found\n";
    exit(2);
}

$tokenable = $pat->tokenable;
echo "Found token id={$pat->id}, tokenable_type={$pat->tokenable_type}, tokenable_id={$pat->tokenable_id}\n";
if ($tokenable) {
    echo "Tokenable: ";
    if (method_exists($tokenable, 'toArray')) {
        echo json_encode($tokenable->only(['id','name','branch_id']), JSON_PRETTY_PRINT) . "\n";
    } else {
        echo get_class($tokenable) . "\n";
    }
} else {
    echo "Tokenable record not found or null\n";
}

if (property_exists($pat, 'expires_at') && $pat->expires_at) {
    echo "Expires at: " . $pat->expires_at . "\n";
}
