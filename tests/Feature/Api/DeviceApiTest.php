<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function actingAsAdmin(): void
    {
        $user = User::factory()->create();

        // Create device management permissions if they don't exist, then grant
        // them to the test user so DevicePolicy checks don't throw PermissionDoesNotExist.
        $permissions = ['view devices', 'create devices', 'update devices', 'delete devices'];
        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->givePermissionTo($permissions);

        Sanctum::actingAs($user, [], 'sanctum');
    }

    private function makeBranch(array $attrs = []): Branch
    {
        return Branch::create(array_merge([
            'name' => 'Main '.uniqid(),
            'location' => 'HQ',
        ], $attrs));
    }

    private function makeDevice(Branch $branch, array $attrs = []): Device
    {
        return Device::create(array_merge([
            'name'      => 'Test Device '.uniqid(),
            'type'      => 'tablet',
            'branch_id' => $branch->id,
            'is_active' => true,
        ], $attrs));
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_devices_index_requires_authentication(): void
    {
        $resp = $this->get('/api/v2/devices');
        $this->assertContains($resp->getStatusCode(), [401, 403, 302]);
    }

    // -------------------------------------------------------------------------
    // Index — pagination / filters
    // -------------------------------------------------------------------------

    public function test_devices_index_returns_paginated_list(): void
    {
        $branch = $this->makeBranch();
        $this->makeDevice($branch, ['name' => 'Device A']);
        $this->makeDevice($branch, ['name' => 'Device B']);
        $this->makeDevice($branch, ['name' => 'Device C']);

        $this->actingAsAdmin();

        $resp = $this->get('/api/v2/devices');
        $resp->assertStatus(200);

        $json = $resp->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertCount(3, $json['data']);
        $this->assertEquals(3, $json['meta']['total']);
        foreach (['total', 'per_page', 'current_page', 'last_page'] as $key) {
            $this->assertArrayHasKey($key, $json['meta']);
        }
    }

    public function test_devices_index_filters_by_type(): void
    {
        $branch = $this->makeBranch();
        $this->makeDevice($branch, ['type' => 'tablet']);
        $this->makeDevice($branch, ['type' => 'relay_printer']);

        $this->actingAsAdmin();

        $resp = $this->get('/api/v2/devices?type=tablet');
        $resp->assertStatus(200);
        $this->assertCount(1, $resp->json('data'));
    }

    // -------------------------------------------------------------------------
    // Statistics
    // -------------------------------------------------------------------------

    public function test_device_statistics_endpoint_returns_required_keys(): void
    {
        $branch = $this->makeBranch();
        $this->makeDevice($branch, ['last_seen_at' => now()]);           // online
        $this->makeDevice($branch, ['last_seen_at' => now()->subHour()]); // offline
        $this->makeDevice($branch, ['is_active' => false]);

        $this->actingAsAdmin();

        $resp = $this->get('/api/v2/devices/statistics');
        $resp->assertStatus(200);

        $json = $resp->json();
        foreach (['total', 'active', 'online', 'offline', 'by_type'] as $key) {
            $this->assertArrayHasKey($key, $json);
        }
        $this->assertEquals(3, $json['total']);
        $this->assertEquals(1, $json['online']);
        $this->assertEquals(2, $json['offline']);
        $this->assertIsArray($json['by_type']);
        foreach (['tablet', 'relay_printer', 'print_bridge', 'direct_printer'] as $type) {
            $this->assertArrayHasKey($type, $json['by_type']);
            $this->assertArrayHasKey('total', $json['by_type'][$type]);
            $this->assertArrayHasKey('online', $json['by_type'][$type]);
        }
    }

    // -------------------------------------------------------------------------
    // By-status
    // -------------------------------------------------------------------------

    public function test_by_status_splits_devices_into_online_and_offline(): void
    {
        $branch = $this->makeBranch();
        $this->makeDevice($branch, ['name' => 'Online One', 'last_seen_at' => now()]);
        $this->makeDevice($branch, ['name' => 'Offline One', 'last_seen_at' => now()->subHour()]);

        $this->actingAsAdmin();

        $resp = $this->get('/api/v2/devices/by-status');
        $resp->assertStatus(200);
        $json = $resp->json();
        $this->assertArrayHasKey('online', $json);
        $this->assertArrayHasKey('offline', $json);
        $this->assertCount(1, $json['online']);
        $this->assertCount(1, $json['offline']);
    }

    // -------------------------------------------------------------------------
    // Store (registration)
    // -------------------------------------------------------------------------

    public function test_store_creates_device_and_returns_one_time_security_code(): void
    {
        $branch = $this->makeBranch();

        $this->actingAsAdmin();

        $resp = $this->postJson('/api/v2/devices', [
            'name'          => 'New Tablet',
            'type'          => 'tablet',
            'security_code' => '123456',
            'branch_id'     => $branch->id,
        ]);

        $resp->assertStatus(201);
        $json = $resp->json();
        $this->assertArrayHasKey('device', $json);
        $this->assertArrayHasKey('security_code', $json);
        $this->assertEquals('123456', $json['security_code']);
        $this->assertDatabaseHas('devices', ['name' => 'New Tablet', 'type' => 'tablet']);
    }

    public function test_store_rejects_invalid_security_code_format(): void
    {
        $branch = $this->makeBranch();
        $this->actingAsAdmin();

        $resp = $this->postJson('/api/v2/devices', [
            'name'          => 'Bad Device',
            'type'          => 'tablet',
            'security_code' => 'abc',
            'branch_id'     => $branch->id,
        ]);

        $resp->assertStatus(422);
        $json = $resp->json();
        $this->assertIsArray($json);
        $this->assertEquals('VALIDATION_ERROR', $json['error']['code'] ?? null);
        $this->assertArrayHasKey('security_code', $json['error']['details'] ?? []);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_device_with_branch(): void
    {
        $branch = $this->makeBranch();
        $device = $this->makeDevice($branch);

        $this->actingAsAdmin();

        $resp = $this->get("/api/v2/devices/{$device->id}");
        $resp->assertStatus(200);

        $json = $resp->json();
        $this->assertEquals($device->id, $json['id']);
        $this->assertArrayHasKey('branch', $json);
        $this->assertEquals($branch->id, $json['branch']['id']);
    }

    // -------------------------------------------------------------------------
    // Health
    // -------------------------------------------------------------------------

    public function test_device_health_returns_online_state_when_recently_seen(): void
    {
        $branch = $this->makeBranch();
        $device = $this->makeDevice($branch, ['last_seen_at' => now()]);

        $this->actingAsAdmin();

        $resp = $this->get("/api/v2/devices/{$device->id}/health");
        $resp->assertStatus(200);

        $json = $resp->json();
        $this->assertTrue($json['online']);
        $this->assertArrayHasKey('last_seen_at', $json);
        $this->assertArrayHasKey('latest_heartbeat', $json);
    }

    public function test_device_health_returns_offline_when_stale(): void
    {
        $branch = $this->makeBranch();
        $device = $this->makeDevice($branch, ['last_seen_at' => now()->subHour()]);

        $this->actingAsAdmin();

        $resp = $this->get("/api/v2/devices/{$device->id}/health");
        $resp->assertStatus(200);
        $this->assertFalse($resp->json('online'));
    }

    public function test_device_health_returns_offline_when_never_seen(): void
    {
        $branch = $this->makeBranch();
        $device = $this->makeDevice($branch, ['last_seen_at' => null]);

        $this->actingAsAdmin();

        $resp = $this->get("/api/v2/devices/{$device->id}/health");
        $resp->assertStatus(200);
        $this->assertFalse($resp->json('online'));
    }

    // -------------------------------------------------------------------------
    // Toggle status
    // -------------------------------------------------------------------------

    public function test_toggle_status_deactivates_device(): void
    {
        $branch = $this->makeBranch();
        $device = $this->makeDevice($branch, ['is_active' => true]);

        $this->actingAsAdmin();

        $resp = $this->postJson("/api/v2/devices/{$device->id}/status", ['is_active' => false]);
        $resp->assertStatus(200);
        $this->assertFalse($resp->json('is_active'));
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'is_active' => false]);
    }

    public function test_toggle_status_validates_is_active_field(): void
    {
        $branch = $this->makeBranch();
        $device = $this->makeDevice($branch);

        $this->actingAsAdmin();

        $resp = $this->postJson("/api/v2/devices/{$device->id}/status", []);
        $resp->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Regenerate security code
    // -------------------------------------------------------------------------

    public function test_regenerate_security_code_returns_new_plain_code(): void
    {
        $branch = $this->makeBranch();
        $device = $this->makeDevice($branch);

        $this->actingAsAdmin();

        $resp = $this->postJson("/api/v2/devices/{$device->id}/security-code");
        $resp->assertStatus(200);
        $this->assertArrayHasKey('security_code', $resp->json());
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $resp->json('security_code'));
    }

    // -------------------------------------------------------------------------
    // Metadata
    // -------------------------------------------------------------------------

    public function test_metadata_returns_active_branches(): void
    {
        $this->makeBranch(['name' => 'Active Branch']);
        $this->makeBranch(['name' => 'Second Branch']);

        $this->actingAsAdmin();

        $resp = $this->get('/api/v2/devices/metadata');
        $resp->assertStatus(200);
        $json = $resp->json();
        $this->assertArrayHasKey('branches', $json);
        $this->assertCount(2, $json['branches']);
        $this->assertEquals('Active Branch', $json['branches'][0]['name']);
    }
}
