<?php

namespace App\Helpers;

class BroadcastConfig
{
    /**
     * Return the client-safe broadcasting configuration.
     *
     * NEVER include: secret, app_id (server-only values).
     *
     * @return array<string, mixed>
     */
    public static function clientPayload(): array
    {
        $reverb = config('broadcasting.connections.reverb');

        if (! $reverb || empty($reverb['key'])) {
            return [];
        }

        return [
            'driver' => 'reverb',
            'key'    => $reverb['key'],
            'host'   => $reverb['options']['host'] ?? null,
            'port'   => (int) ($reverb['options']['port'] ?? 6001),
            'scheme' => $reverb['options']['scheme'] ?? 'https',
            'auth_endpoint' => '/broadcasting/auth',
        ];
    }
}
