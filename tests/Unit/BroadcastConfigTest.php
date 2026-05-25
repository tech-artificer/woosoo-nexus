<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\BroadcastConfig;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BroadcastConfigTest extends TestCase
{
    public function test_client_payload_uses_public_reverb_host_not_internal_publish_host(): void
    {
        Config::set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'public-client-key',
            'secret' => 'server-secret',
            'app_id' => 'woosoo',
            'options' => [
                'host' => 'reverb',
                'port' => 8080,
                'scheme' => 'http',
                'public_host' => '192.168.100.42',
                'public_port' => 443,
                'public_scheme' => 'https',
                'useTLS' => false,
            ],
        ]);

        $payload = BroadcastConfig::clientPayload();

        $this->assertSame('192.168.100.42', $payload['host']);
        $this->assertSame(443, $payload['port']);
        $this->assertSame('https', $payload['scheme']);
    }
}
