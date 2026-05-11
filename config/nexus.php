<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nexus PrintEvent flow
    |--------------------------------------------------------------------------
    |
    | Keep PrintEvent infrastructure available for future work, but disable the
    | runtime path by default while woosoo-print-bridge remains the only active
    | MVP print execution owner.
    |
    */
    'print_events_enabled' => env('NEXUS_PRINT_EVENTS_ENABLED', false),
];
