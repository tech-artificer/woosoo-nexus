<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAuthLookupByIpTest extends TestCase
{
    use RefreshDatabase;

    private ?string $previousAllowClientSuppliedIp = null;

    private ?string $previousAllowedPrivateSubnets = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousAllowClientSuppliedIp = getenv('DEVICE_ALLOW_CLIENT_SUPPLIED_IP') !== false
            ? (string) getenv('DEVICE_ALLOW_CLIENT_SUPPLIED_IP')
            : null;
        $this->previousAllowedPrivateSubnets = getenv('DEVICE_ALLOWED_PRIVATE_SUBNETS') !== false
            ? (string) getenv('DEVICE_ALLOWED_PRIVATE_SUBNETS')
            : null;

        putenv('DEVICE_ALLOW_CLIENT_SUPPLIED_IP=0');
        putenv('DEVICE_ALLOWED_PRIVATE_SUBNETS=');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('DEVICE_ALLOW_CLIENT_SUPPLIED_IP', $this->previousAllowClientSuppliedIp);
        $this->restoreEnv('DEVICE_ALLOWED_PRIVATE_SUBNETS', $this->previousAllowedPrivateSubnets);

        parent::tearDown();
    }

    public function test_lookup_by_ip_ignores_untrusted_client_supplied_private_ip(): void
    {
        Branch::create([
            'name' => 'Lookup Branch',
            'location' => 'HQ',
        ]);

        Device::create([
            'name' => 'Lookup Device',
            'ip_address' => '192.168.50.10',
            'is_active' => true,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
        ])->getJson('/api/device/lookup-by-ip?ip_address=192.168.50.10');

        $response->assertOk();
        $response->assertJsonPath('found', false);
        $response->assertJsonPath('ip_used', '127.0.0.1');
    }

    private function restoreEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);

            return;
        }

        putenv($key . '=' . $value);
    }
}