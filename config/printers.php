<?php

return [
    'cashier' => [
        'type' => 'network',
        'ip' => '127.0.0.1',
        'port' => 9100
    ],

    'kitchen' => [
        [  
            'type' => 'bluetooth',
            'com' => 'COM3'
        ],
        [
            'type' => 'bluetooth',
            'com' => 'COM4'
        ]
    ]
];