<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_v1_store_auto_generates_security_code_when_omitted(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'location' => 'HQ',
        ]);

        $authDevice = Device::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($authDevice, [], 'device');

        $response = $this->postJson('/api/devices', [
            'name' => 'Kitchen Tablet',
            'ip_address' => '10.0.0.55',
            'port' => 9100,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'device' => ['id', 'device_uuid', 'name'],
                'security_code',
            ]);

        $plainCode = (string) $response->json('security_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $plainCode);

        $device = Device::query()->where('name', 'Kitchen Tablet')->firstOrFail();

        $this->assertNotNull($device->security_code);
        $this->assertTrue(Hash::check($plainCode, (string) $device->security_code));
        $this->assertNotNull($device->security_code_generated_at);
    }

    public function test_v1_store_rejects_duplicate_plain_security_code_against_hashed_records(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'location' => 'HQ',
        ]);

        $authDevice = Device::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        Device::factory()->create([
            'branch_id' => $branch->id,
            'security_code' => Hash::make('123456'),
            'security_code_generated_at' => now(),
        ]);

        Sanctum::actingAs($authDevice, [], 'device');

        $response = $this->postJson('/api/devices', [
            'name' => 'Duplicate Tablet',
            'ip_address' => '10.0.0.56',
            'security_code' => '123456',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('errors.security_code', 'This code is in use');

        $this->assertDatabaseMissing('devices', [
            'name' => 'Duplicate Tablet',
            'ip_address' => '10.0.0.56',
        ]);
    }
}
