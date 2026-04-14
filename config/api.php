<?php

return [
    'url' => env('APP_URL', ''),

    'krypton' => [
        'terminal_id' => (int) env('KRYPTON_TERMINAL_ID', 1),
        'tax_rate'    => (float) env('KRYPTON_TAX_RATE', 0.10),
    ],
];