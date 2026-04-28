<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAuthTrustedIpConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_by_ip_uses_configured_trusted_client_ip_rules(): void
    {
        config()->set('device.allow_client_supplied_ip', true);
        config()->set('device.allowed_private_subnets', '192.168.50.0/24');

        Branch::create([
            'name' => 'Trusted IP Branch',
            'location' => 'HQ',
        ]);

        Device::create([
            'name' => 'Trusted IP Device',
            'ip_address' => '192.168.50.10',
            'is_active' => true,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
        ])->getJson('/api/device/lookup-by-ip?ip_address=192.168.50.10');

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonPath('ip_used', '192.168.50.10');
    }
}
