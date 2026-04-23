<?php

use App\Support\PublicOrigin;

return [
    'url' => env('APP_URL', PublicOrigin::appUrl()),

    'krypton' => [
        'terminal_id' => (int) env('KRYPTON_TERMINAL_ID', 1),
        'tax_rate'    => (float) env('KRYPTON_TAX_RATE', 0.10),
    ],
];