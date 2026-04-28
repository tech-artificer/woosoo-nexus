<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Device Auth IP Trust
    |--------------------------------------------------------------------------
    |
    | In proxied/containerized deployments, request()->ip() may resolve to the
    | reverse-proxy bridge address. These settings control whether the API may
    | trust a client-supplied private LAN IP (ip_address query/body field).
    |
    */
    'allow_client_supplied_ip' => (bool) env('DEVICE_ALLOW_CLIENT_SUPPLIED_IP', false),
    'allowed_private_subnets' => (string) env('DEVICE_ALLOWED_PRIVATE_SUBNETS', ''),

    /*
    |--------------------------------------------------------------------------
    | Global Auth Passcode
    |--------------------------------------------------------------------------
    |
    | A shared numeric passcode required by all tablets at registration and
    | login. When null/empty, registration is blocked (secure-off default).
    |
    */
    'auth_passcode' => env('DEVICE_AUTH_PASSCODE', null),
];
