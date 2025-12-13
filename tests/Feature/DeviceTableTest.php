<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Device;
use App\Models\Branch;

class DeviceTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_device_table()
    {
        $response = $this->getJson('/api/device/table?ip=127.0.0.1');
        $response->assertStatus(401);
    }

    public function test_authenticated_device_can_lookup_by_ip_and_get_device_resource()
    {
        // Ensure a Branch exists because Device::creating expects one
        Branch::create(['name' => 'Main', 'location' => 'HQ']);
        // No Table model factory in this codebase; set table_id directly
        $tableId = 100;
        $device = Device::create([
            'name' => 'Device A',
            'ip_address' => '192.168.0.50',
            'is_active' => true,
            'table_id' => $tableId,
        ]);

        $token = $device->createToken('test-token')->plainTextToken;

        $resp = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/device/table?ip=192.168.0.50');

        $resp->assertStatus(200);
        $resp->assertJsonStructure([
            'success',
            'data' => [
                'device' => ['id', 'name', 'device_uuid'],
                'table',
                'ip_used'
            ]
        ]);

        $this->assertTrue($resp->json('success') === true);
        $this->assertEquals('192.168.0.50', $resp->json('data.ip_used'));
        $this->assertEquals($device->id, $resp->json('data.device.id'));
        // top-level 'table' moved under data; will be null when related Table model isn't available
        $this->assertNull($resp->json('data.table'));
    }

    public function test_authenticated_lookup_returns_404_for_unknown_ip()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);
        $device = Device::create([
            'name' => 'Device B',
            'ip_address' => '192.168.1.2',
            'is_active' => true,
        ]);
        $token = $device->createToken('t')->plainTextToken;

        $resp = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/device/table?ip=10.255.255.1');

        $resp->assertStatus(404);
        $resp->assertJson([
            'success' => false,
            'message' => 'Device not found',
        ]);
    }
}
