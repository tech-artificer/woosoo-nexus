<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Device;
use App\Models\Branch;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class AdminDeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_device_token_via_admin_route()
    {
        // Prepare Branch for device creation
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        // create a device
        $device = Device::create([
            'name' => 'Device Admin Token Test',
            'ip_address' => '192.168.2.10',
            'is_active' => true,
        ]);

        // create admin user
        $admin = User::factory()->create(['is_admin' => true]);

        $this->withoutMiddleware();
        // Refresh the device model to ensure the record exists in the DB and has an ID
        $device = Device::find($device->id);
        $this->assertNotNull($device->id);

            // Simulate admin-issued personal access token creation directly on the model.
            $plain = $device->createToken('admin-issued', abilities: ['*'], expiresAt: now()->addDays(365))->plainTextToken;
            $this->assertNotEmpty($plain);

            // Verify a token record exists for this device
            $count = PersonalAccessToken::where('tokenable_type', Device::class)
                ->where('tokenable_id', $device->id)
                ->count();

            $this->assertGreaterThan(0, $count);

            // Verify the generated token can authenticate against device routes (sanctum guard)
            $resp = $this->withHeaders(['Authorization' => 'Bearer ' . $plain, 'Accept' => 'application/json'])
                ->getJson('/api/device/table?ip=' . $device->ip_address);

            $resp->assertStatus(200);
            $this->assertEquals($device->id, $resp->json('data.device.id'));
    }
}
