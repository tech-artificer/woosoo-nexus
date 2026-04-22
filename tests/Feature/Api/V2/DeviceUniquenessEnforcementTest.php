<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V2;

use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeviceUniquenessEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);

        // Seed permissions required by DevicePolicy (not present in fresh RefreshDatabase)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $devicePermissions = ['devices.view', 'devices.register', 'devices.update', 'devices.delete', 'devices.restore'];
        foreach ($devicePermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->admin()->create();
        $this->admin->givePermissionTo($devicePermissions);
    }

    /**
     * Test: Create endpoint rejects duplicate security code with 409 Conflict
     * Purpose: Prevent ambiguous registration collisions
     */
    public function test_v2_create_device_with_duplicate_security_code_returns_409(): void
    {
        // Arrange: Create existing device with known hashed code
        Device::factory()->create(['security_code' => Hash::make('123456')]);

        // Act: Attempt to create another device with same plain code
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v2/devices', [
                'name' => 'New Device',
                'security_code' => '123456',
                'type' => 'tablet',
            ]);

        // Assert: Should reject with 409
        $response->assertStatus(409)
            ->assertJsonPath('message', 'Security code already assigned to another device')
            ->assertJsonPath('errors.security_code', 'This code is in use');
    }

    /**
     * Test: Create endpoint succeeds with unique security code
     * Purpose: Confirm happy path still works
     */
    public function test_v2_create_device_with_unique_security_code_succeeds(): void
    {
        // Act: Create device with unique 6-digit code
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v2/devices', [
                'name' => 'New Device',
                'security_code' => '654321',
                'type' => 'tablet',
            ]);

        // Assert: Should succeed with 201 (controller returns 'device' + 'security_code')
        $response->assertStatus(201)
            ->assertJsonStructure([
                'device' => [
                    'id',
                    'device_uuid',
                    'name',
                    'type',
                    'last_seen_at',
                ],
                'security_code',
            ])
            ->assertJsonPath('device.name', 'New Device');

        // Verify device was created
        $this->assertDatabaseHas('devices', [
            'name' => 'New Device',
            'type' => 'tablet',
        ]);
    }

    /**
     * Test: Rotate endpoint rejects duplicate security code with 409 Conflict
     * Purpose: Prevent collision when rotating codes
     */
    public function test_v2_rotate_device_code_with_duplicate_returns_409(): void
    {
        // Arrange: Create two devices with distinct hashed codes
        $device1 = Device::factory()->create(['security_code' => Hash::make('111111')]);
        Device::factory()->create(['security_code' => Hash::make('222222')]);

        // Act: Attempt to rotate device1 to device2's plain code
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson(
                "/api/v2/devices/{$device1->id}/rotate-security-code",
                ['security_code' => '222222']
            );

        // Assert: Should reject with 409
        $response->assertStatus(409)
            ->assertJsonPath('message', 'Security code already assigned to another device')
            ->assertJsonPath('errors.security_code', 'This code is in use');

        // Verify original code unchanged
        $this->assertEquals(
            $device1->security_code,
            Device::find($device1->id)->security_code
        );
    }

    /**
     * Test: Rotate endpoint allows device to re-submit its own code (retry scenario)
     * Purpose: Device can be retried without collision error
     */
    public function test_v2_rotate_device_to_own_code_is_allowed(): void
    {
        // Arrange: Create device with known hashed code
        $device = Device::factory()->create(['security_code' => Hash::make('123456')]);

        // Act: Rotate to same plain code
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson(
                "/api/v2/devices/{$device->id}/rotate-security-code",
                ['security_code' => '123456']
            );

        // Assert: Should succeed (not flagged as duplicate against other devices)
        $response->assertStatus(200)
            ->assertJsonPath('message', 'Security code rotated successfully');
    }

    /**
     * Test: Rotate endpoint succeeds with unique security code
     * Purpose: Confirm happy path for code rotation
     */
    public function test_v2_rotate_device_code_with_unique_code_succeeds(): void
    {
        // Arrange: Create device with known hashed code
        $device = Device::factory()->create(['security_code' => Hash::make('111111')]);

        // Act: Rotate to a different unique 6-digit code
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson(
                "/api/v2/devices/{$device->id}/rotate-security-code",
                ['security_code' => '999999']
            );

        // Assert: Should succeed
        $response->assertStatus(200)
            ->assertJsonPath('message', 'Security code rotated successfully')
            ->assertJsonStructure(['data']);

        // Verify device still exists in DB
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }

    /**
     * Test: Admin create with duplicate code fails
     * Purpose: Admin web path enforces same uniqueness as API
     */
    public function test_admin_create_device_with_duplicate_code_fails(): void
    {
        // Arrange: Create existing device with known hashed code
        Device::factory()->create(['security_code' => Hash::make('123456')]);

        // Act: Admin attempts to create with same plain code
        $response = $this->actingAs($this->admin)
            ->post('/devices', [
                'name' => 'New Device',
                'ip_address' => '10.0.1.100',
                'security_code' => '123456',
                'type' => 'tablet',
            ]);

        // Assert: Should redirect with security_code error
        $response->assertRedirect()
            ->assertSessionHasErrors('security_code');
    }

    /**
     * Test: Admin create with unique code succeeds
     * Purpose: Admin happy path works
     */
    public function test_admin_create_device_with_unique_code_succeeds(): void
    {
        // Act: Admin creates device with unique 6-digit code
        $response = $this->actingAs($this->admin)
            ->post('/devices', [
                'name' => 'Admin Device',
                'ip_address' => '10.0.1.200',
                'security_code' => '654321',
                'type' => 'tablet',
            ]);

        // Assert: Should redirect after creation
        $response->assertRedirect();

        // Verify device was created
        $this->assertDatabaseHas('devices', ['name' => 'Admin Device']);
    }

    /**
     * Test: Controller hash-check catches duplicate security code → 409
     * Purpose: Verify controller-level uniqueness fires (hashed code comparison, not SQL unique)
     */
    public function test_controller_rejects_duplicate_security_code_with_409(): void
    {
        // Arrange: Create device with known hashed code
        Device::factory()->create(['security_code' => Hash::make('123456')]);

        // Act: Submit same plain code
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v2/devices', [
                'name' => 'Device',
                'security_code' => '123456',
                'type' => 'tablet',
            ]);

        // Assert: Controller hash-check catches it → 409
        $response->assertStatus(409);
    }

    /**
     * Test: Second sequential create with same code returns 409
     * Purpose: Documents expected outcome of race condition (one succeeds, one fails)
     */
    public function test_concurrent_creates_with_same_code_only_one_succeeds(): void
    {
        // Simulate: First request already created device with code '123456'
        Device::factory()->create(['security_code' => Hash::make('123456')]);

        // Simulate: Second request attempts same plain code
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v2/devices', [
                'name' => 'Concurrent Device',
                'security_code' => '123456',
                'type' => 'tablet',
            ]);

        // Assert: Second request fails with 409
        $response->assertStatus(409);
    }
}
