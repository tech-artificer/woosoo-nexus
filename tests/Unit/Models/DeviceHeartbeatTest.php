<?php

namespace Tests\Unit\Models;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_belongs_to_device(): void
    {
        $device = Device::factory()->create();

        $hb = DeviceHeartbeat::create([
            'device_id'   => $device->id,
            'recorded_at' => now(),
        ]);

        $this->assertEquals($device->id, $hb->device->id);
    }

    public function test_metadata_cast_is_array(): void
    {
        $device = Device::factory()->create();

        $hb = DeviceHeartbeat::create([
            'device_id'   => $device->id,
            'recorded_at' => now(),
            'metadata'    => ['battery' => 85, 'wifi' => -62],
        ]);

        $this->assertIsArray($hb->fresh()->metadata);
        $this->assertEquals(85, $hb->fresh()->metadata['battery']);
    }

    public function test_storage_percent_computed_correctly(): void
    {
        $device = Device::factory()->create();

        $hb = DeviceHeartbeat::create([
            'device_id'           => $device->id,
            'recorded_at'         => now(),
            'storage_used_bytes'  => 512 * 1024 * 1024,  // 512 MB
            'storage_total_bytes' => 1024 * 1024 * 1024, // 1 GB
        ]);

        $this->assertEquals(50.0, $hb->storage_percent);
    }

    public function test_storage_percent_is_null_when_data_missing(): void
    {
        $device = Device::factory()->create();

        $hb = DeviceHeartbeat::create([
            'device_id'   => $device->id,
            'recorded_at' => now(),
        ]);

        $this->assertNull($hb->storage_percent);
    }
}
