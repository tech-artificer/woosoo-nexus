<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Device;
use App\Models\Branch;
use App\Support\DeviceSecurityCode;

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

    }

    public function test_register_claims_precreated_device_by_security_code_and_updates_ip_metadata(): void
    {
        $device = Device::create(array_merge([
            'name' => 'Table 7 Tablet',
            'type' => 'tablet',
            'device_uuid' => 'uuid-claim-001',
            'ip_address' => '192.168.100.10',
            'is_active' => true,
        ], DeviceSecurityCode::attributesFor('123456')));

        $response = $this->postJson('/api/devices/register', [
            'security_code' => '123456',
            'app_version' => '1.0.0',
            'ip_address' => '192.168.100.150',
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('token', $json);
        $this->assertNotEmpty($json['token']);
        $this->assertSame($device->id, $json['device']['id']);
        $this->assertSame('192.168.100.150', $json['ip_used']);

        $this->assertCount(1, Device::all());
        $updatedDevice = Device::find($device->id);
        $this->assertNotNull($updatedDevice);
        $this->assertSame('Table 7 Tablet', $updatedDevice->name);
        $this->assertSame('tablet', $updatedDevice->type);
        $this->assertSame('192.168.100.150', $updatedDevice->ip_address);
        $this->assertSame('192.168.100.150', $updatedDevice->last_ip_address);
        $this->assertNotNull($updatedDevice->last_seen_at);
        $this->assertNull($updatedDevice->security_code);
        $this->assertNull($updatedDevice->security_code_lookup);
    }

    public function test_register_rejects_unknown_security_code_without_creating_device(): void
    {
        $response = $this->postJson('/api/devices/register', [
            'security_code' => '654321',
            'app_version' => '1.0.0',
        ]);

        $response->assertStatus(422);
        $json = $response->json();
        $this->assertFalse($json['success'] ?? true);
        $this->assertStringContainsString('Invalid security code', $json['message'] ?? '');
        $this->assertCount(0, Device::all());
    }

    public function test_register_accepts_passcode_field_as_setup_code_alias(): void
    {
        $device = Device::create(array_merge([
            'name' => 'Alias Tablet',
            'type' => 'tablet',
            'device_uuid' => 'uuid-alias-001',
            'ip_address' => '192.168.100.151',
            'is_active' => true,
        ], DeviceSecurityCode::attributesFor('123456')));

        $response = $this->postJson('/api/devices/register', [
            'passcode' => '123456',
            'app_version' => '1.0.0',
            'ip_address' => '192.168.100.152',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('device.id', $device->id);
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

    public function test_register_rejects_code_only_payload_with_422(): void
    {
        // CT-01/CT-06 alias sunset: `code` field no longer accepted — must return 422.
        $response = $this->postJson('/api/devices/register', [
            'code' => '123456',
            'app_version' => '1.0.0',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_supports_legacy_hashed_security_code_without_lookup_hash(): void
    {
        $device = Device::create([
            'name' => 'Legacy Code Tablet',
            'type' => 'tablet',
            'device_uuid' => 'uuid-legacy-code',
            'is_active' => true,
            'ip_address' => '10.0.0.1',
            'security_code' => \Illuminate\Support\Facades\Hash::make('123456'),
            'last_seen_at' => now()->subHours(5),
        ]);

        $response = $this->postJson('/api/devices/register', [
            'security_code' => '123456',
            'app_version' => '1.0.0',
            'ip_address' => '10.0.0.2',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('device.id', $device->id);

        $updatedDevice = Device::find($device->id);
        $this->assertSame('10.0.0.2', $updatedDevice?->ip_address);
        $this->assertNull($updatedDevice?->security_code);
        $this->assertNotNull($updatedDevice?->last_seen_at);
    }
}
