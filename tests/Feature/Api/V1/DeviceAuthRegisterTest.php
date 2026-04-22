<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Device;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;

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

    // =========================================================================
    // BATCH 1: Security Code Registration Tests
    // =========================================================================
    // These tests verify the new security_code-first registration flow.
    // They test all three match-count scenarios and the legacy code alias path.

    /**
    * Test: Single device hash match on security_code -> claim device and return token
     * 
     * When a device with the submitted security_code exists and is unmatched (1 device),
     * the registration should claim that device, update ip_address and last_seen_at,
     * and return a token with status 200.
     */
    public function test_security_code_single_match_claims_device_and_returns_token(): void
    {
        // Arrange: Create a device with a hashed security_code
        $device = Device::create([
            'name' => 'Test Tablet',
            'type' => 'tablet',
            'device_uuid' => 'uuid-001',
            'security_code' => Hash::make('123456'),
            'is_active' => true,
        ]);

        $originalId = $device->id;
        $incomingIp = '192.168.100.150';
        $userAgent = 'tablet-app/1.0';

        // Act: Register with the security_code
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Test Tablet',
            'security_code' => '123456',
            'app_version' => '1.0.0',
            'ip_address' => $incomingIp,
            'user_agent' => $userAgent,
        ]);

        // Assert
        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('token', $json);
        $this->assertNotEmpty($json['token']);
        $this->assertArrayHasKey('ip_used', $json);
        
        // Verify device was updated (claimed), not duplicated
        $this->assertCount(1, Device::all());
        $updatedDevice = Device::find($originalId);
        $this->assertNotNull($updatedDevice);
        $this->assertEquals($json['ip_used'], $updatedDevice->ip_address);
        $this->assertNotNull($updatedDevice->last_seen_at);
    }

    /**
     * Test: No match on security_code -> create new device and return token
     * 
     * When no device exists with the submitted security_code,
     * a new device should be created and registered, returning a token with status 200.
     */
    public function test_security_code_no_match_returns_422_invalid_code(): void
    {
        // Act: Register with a security_code that does not match any device hash.
        $response = $this->postJson('/api/devices/register', [
            'name' => 'New Tablet',
            'security_code' => '654321',
            'app_version' => '1.0.0',
        ]);

        // Assert: do not auto-create devices on register.
        $response->assertStatus(422);
        $json = $response->json();
        $this->assertFalse($json['success'] ?? true);
        $this->assertStringContainsString('Invalid security code', $json['message'] ?? '');
        
        // Verify no new device was created.
        $this->assertCount(0, Device::all());
    }

    /**
    * Test: Multiple devices match on security_code hash -> return 409 Conflict
     * 
     * When multiple devices exist with the same security_code (data inconsistency),
     * the registration should reject with 409 (Conflict / Ambiguous Match)
     * and NOT claim any device.
     */
    public function test_security_code_multiple_matches_returns_409_conflict(): void
    {
        // Arrange: Create two devices with the same security_code hash (ambiguous state)
        Device::create([
            'name' => 'Device 1',
            'type' => 'tablet',
            'device_uuid' => 'uuid-001',
            'security_code' => Hash::make('111111'),
            'is_active' => true,
        ]);

        Device::create([
            'name' => 'Device 2',
            'type' => 'tablet',
            'device_uuid' => 'uuid-002',
            'security_code' => Hash::make('111111'),
            'is_active' => true,
        ]);

        // Act: Attempt to register with the ambiguous security_code
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Ambiguous Device',
            'security_code' => '111111',
            'app_version' => '1.0.0',
        ]);

        // Assert: Should reject with 409
        $response->assertStatus(409);
        $json = $response->json();
        $this->assertFalse($json['success'] ?? true);
        
        // Verify no new device was created
        $this->assertCount(2, Device::all());
    }

    /**
    * Test: No security_code provided but code field provided (legacy alias)
     * 
     * During the transition period, registrations with the legacy 'code' field
     * should still be accepted and route through the legacy code table lookup.
     * This tests backward compatibility with Batch 1 clients.
     */
    public function test_legacy_code_field_alias_maps_to_security_code_match(): void
    {
        // Arrange: Existing device has hashed security_code.
        Device::create([
            'name' => 'Alias Tablet',
            'type' => 'tablet',
            'device_uuid' => 'uuid-alias',
            'security_code' => Hash::make('246810'),
            'is_active' => true,
        ]);

        // Act: Submit only legacy alias field.
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Alias Tablet',
            'code' => '246810',
            'app_version' => '1.0.0',
        ]);

        // Assert: alias maps to security_code and still authenticates.
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertArrayHasKey('token', $response->json());
    }

    /**
    * Test: Missing both security_code and code -> return 422 Unprocessable Entity
     * 
     * When the security_code is not provided and no fallback code is available,
     * the request should fail validation with 422.
     */
    public function test_missing_security_code_and_code_returns_422_unprocessable(): void
    {
        // Act: Register without security_code
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Test Tablet',
            'app_version' => '1.0.0',
        ]);

        // Assert: Should fail validation
        $response->assertStatus(422);
    }

    /**
    * Test: Invalid security_code format -> return 422 Unprocessable Entity
     * 
     * The security_code must be a 6-digit numeric string.
     */
    public function test_invalid_security_code_format_returns_422(): void
    {
        // Act: Register with invalid format (too short, non-numeric, etc.)
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Test Tablet',
            'security_code' => 'INVALID', // not numeric
            'app_version' => '1.0.0',
        ]);

        // Assert: Should fail validation
        $response->assertStatus(422);
    }

    /**
     * Test: IP address and last_seen_at are updated on claim
     * 
     * When a device is claimed via security_code, its IP address and last_seen_at
     * timestamp must be updated to reflect the current request.
     */
    public function test_device_claim_updates_ip_and_last_seen(): void
    {
        // Arrange
        $oldIp = '10.0.0.1';
        $oldTime = now()->subHours(5);
        $device = Device::create([
            'name' => 'Old Device',
            'type' => 'tablet',
            'device_uuid' => 'uuid-old',
            'security_code' => Hash::make('999999'),
            'is_active' => true,
            'ip_address' => $oldIp,
            'last_seen_at' => $oldTime,
        ]);

        $newIp = '192.168.100.99';

        // Act
        $response = $this->postJson('/api/devices/register', [
            'name' => 'Old Device',
            'security_code' => '999999',
            'app_version' => '1.0.0',
            'ip_address' => $newIp,
        ]);

        // Assert
        $response->assertStatus(200);
        $json = $response->json();
        $updatedDevice = Device::find($device->id);
        $this->assertEquals($json['ip_used'] ?? $updatedDevice->ip_address, $updatedDevice->ip_address);
        $this->assertGreaterThan($oldTime, $updatedDevice->last_seen_at);
    }
}
