<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Branch;
use App\Models\Device;
use Illuminate\Support\Facades\Hash;

class DeviceRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_register_rate_limit_is_enforced(): void
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $server = ['REMOTE_ADDR' => '192.0.2.55'];

        $codes = [];
        for ($i = 0; $i < 11; $i++) {
            $code = str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT);
            Device::create([
                'branch_id' => 1,
                'name' => 'Rate Limit Device Seed ' . $i,
                'security_code' => Hash::make($code),
                'is_active' => true,
            ]);
            $codes[] = $code;
        }

        for ($i = 0; $i < 10; $i++) {
            $payload = [
                'name' => 'Rate Limit Device ' . $i,
                'security_code' => $codes[$i],
                'app_version' => '1.0.0',
            ];
            $this->withServerVariables($server)
                ->postJson('/api/devices/register', $payload);
        }

        $payload = [
            'name' => 'Rate Limit Device 10',
            'security_code' => $codes[10],
            'app_version' => '1.0.0',
        ];

        $response = $this->withServerVariables($server)
            ->postJson('/api/devices/register', $payload);

        $response->assertStatus(429);
    }
}
