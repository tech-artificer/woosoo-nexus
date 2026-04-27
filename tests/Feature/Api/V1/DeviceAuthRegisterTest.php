<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Device;
use App\Models\Branch;

class DeviceAuthRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Device model boot enforces exactly one local branch.
        Branch::create([
            'name' => 'Test Branch',
            'location' => 'Test Location',
        ]);

        config()->set('device.auth_passcode', '123456');
    }

    public function test_register_with_valid_global_passcode_creates_device_for_new_ip_and_returns_token(): void
    {
        $incomingIp = '192.168.100.150';
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Test Tablet',
            'passcode' => '123456',
            'app_version' => '1.0.0',
            'ip_address' => $incomingIp,
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('token', $json);
        $this->assertNotEmpty($json['token']);

        $this->assertCount(1, Device::all());
        $updatedDevice = Device::query()->first();
        $this->assertNotNull($updatedDevice);
        $this->assertSame('Test Tablet', $updatedDevice->name);
        $this->assertSame('tablet', $updatedDevice->type);
        $this->assertEquals($json['ip_used'], $updatedDevice->ip_address);
        $this->assertNotNull($updatedDevice->last_seen_at);
    }

    public function test_register_with_valid_global_passcode_reuses_existing_device_by_ip(): void
    {
        $device = Device::create([
            'name' => 'Existing Tablet',
            'type' => 'tablet',
            'device_uuid' => 'uuid-001',
            'ip_address' => '192.168.100.151',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/devices/register', [
            'name' => 'Existing Tablet',
            'passcode' => '123456',
            'app_version' => '1.0.0',
            'ip_address' => '192.168.100.151',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, Device::all());
        $updatedDevice = Device::find($device->id);
        $this->assertNotNull($updatedDevice);
        $this->assertNotNull($updatedDevice->last_seen_at);
    }

    public function test_register_rejects_invalid_global_passcode(): void
    {
        $response = $this->postJson('/api/devices/register', [
            'name' => 'New Tablet',
            'passcode' => '654321',
            'app_version' => '1.0.0',
        ]);

        $response->assertStatus(422);
        $json = $response->json();
        $this->assertFalse($json['success'] ?? true);
        $this->assertStringContainsString('Invalid passcode', $json['message'] ?? '');
        $this->assertCount(0, Device::all());
    }

    public function test_legacy_security_code_alias_maps_to_global_passcode(): void
    {
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Alias Tablet',
            'security_code' => '123456',
            'app_version' => '1.0.0',
            'ip_address' => '192.168.100.152',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertArrayHasKey('token', $response->json());
    }

    public function test_missing_passcode_aliases_returns_422_unprocessable(): void
    {
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Test Tablet',
            'app_version' => '1.0.0',
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_passcode_format_returns_422(): void
    {
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Test Tablet',
            'passcode' => 'INVALID',
            'app_version' => '1.0.0',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_uses_ip_address_and_updates_last_seen(): void
    {
        $device = Device::create([
            'name' => 'Old Device',
            'type' => 'tablet',
            'device_uuid' => 'uuid-old',
            'is_active' => true,
            'ip_address' => '10.0.0.1',
            'last_seen_at' => now()->subHours(5),
        ]);

        $response = $this->postJson('/api/devices/register', [
            'name' => 'Old Device',
            'passcode' => '123456',
            'app_version' => '1.0.0',
            'ip_address' => '10.0.0.1',
        ]);

        $response->assertStatus(200);
        $updatedDevice = Device::find($device->id);
        $this->assertSame('10.0.0.1', $updatedDevice?->ip_address);
        $this->assertNotNull($updatedDevice?->last_seen_at);
    }
}
