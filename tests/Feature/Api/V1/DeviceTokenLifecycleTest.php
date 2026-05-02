<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Device;
use App\Support\DeviceSecurityCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class DeviceTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);
    }

    /**
     * Test: Register issues a token with a 30-day expiry
     * Purpose: Verify token has correct expiry after registration
     */
    public function test_register_issues_token_with_30_day_expiry(): void
    {
        $device = Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.160',
        ]);
        $device->update(DeviceSecurityCode::attributesFor('123456'));

        $response = $this->postJson('/api/devices/register', [
            'security_code' => '123456',
            'ip_address' => '192.168.100.160',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'expires_at']);

        // Token record must have an expires_at within ~30 days
        $token = PersonalAccessToken::where('tokenable_type', Device::class)
            ->where('tokenable_id', $device->id)
            ->latest()
            ->first();

        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue(
            $token->expires_at->between(now()->addDays(29), now()->addDays(31)),
            "Token expiry should be ~30 days from now, got: {$token->expires_at}"
        );
    }

    /**
     * Test: Re-register revokes all prior tokens before issuing a new one
     * Purpose: Verify active and expired tokens are purged on each register call
     */
    public function test_register_purges_existing_tokens_on_reregister(): void
    {
        $device = Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.161',
        ]);
        $device->update(DeviceSecurityCode::attributesFor('123456'));

        // Seed both active and expired tokens.
        $device->createToken('old-active', expiresAt: now()->addDays(30));
        $device->createToken('old-expired', expiresAt: now()->subDay());

        $this->assertCount(2, $device->tokens()->get());

        // Re-register
        $response = $this->postJson('/api/devices/register', [
            'security_code' => '123456',
            'ip_address' => '192.168.100.161',
        ]);

        $response->assertStatus(200);

        // Prior tokens should be gone; only the new one remains.
        $remaining = PersonalAccessToken::where('tokenable_type', Device::class)
            ->where('tokenable_id', $device->id)
            ->get();

        $this->assertCount(1, $remaining);
        $this->assertTrue($remaining->first()->expires_at->isFuture());
    }

    /**
     * Test: Refresh revokes the current token and issues a new 7-day token
     * Purpose: Verify refresh rolls the token correctly
     */
    public function test_refresh_revokes_current_token_and_issues_new_one(): void
    {
        $device = Device::factory()->create(['is_active' => true]);
        $oldToken = $device->createToken('device-auth', expiresAt: now()->addDays(30))->plainTextToken;

        $oldTokenId = PersonalAccessToken::where('tokenable_id', $device->id)->first()->id;

        $response = $this->withToken($oldToken)
            ->postJson('/api/devices/refresh');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['token', 'expires_at']);

        // Old token must be deleted
        $this->assertNull(PersonalAccessToken::find($oldTokenId));

        // New token must exist with ~7-day expiry
        $newToken = PersonalAccessToken::where('tokenable_type', Device::class)
            ->where('tokenable_id', $device->id)
            ->latest()
            ->first();

        $this->assertNotNull($newToken);
        $this->assertTrue(
            $newToken->expires_at->between(now()->addDays(6), now()->addDays(8)),
            "Refresh token expiry should be ~7 days, got: {$newToken->expires_at}"
        );
    }

    /**
     * Test: Expired token is rejected by sanctum-protected routes
     * Purpose: Verify authentication gate rejects stale tokens
     */
    public function test_expired_token_is_rejected_with_401(): void
    {
        $device = Device::factory()->create(['is_active' => true]);

        // Issue a token that expired yesterday
        $expiredPlain = $device->createToken('device-auth', expiresAt: now()->subDay())->plainTextToken;

        $response = $this->withToken($expiredPlain)
            ->postJson('/api/devices/refresh');

        $response->assertStatus(401);
    }

    /**
     * Test: Active (non-expired) token successfully refreshes
     * Purpose: Happy path — valid token gets a new token back
     */
    public function test_valid_token_can_refresh(): void
    {
        $device = Device::factory()->create(['is_active' => true]);
        $plainToken = $device->createToken('device-auth', expiresAt: now()->addDays(30))->plainTextToken;

        $response = $this->withToken($plainToken)
            ->postJson('/api/devices/refresh');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $newPlain = $response->json('token');
        $this->assertNotEmpty($newPlain);
        $this->assertNotEquals($plainToken, $newPlain, 'Refreshed token should differ from old token');
    }

    public function test_claimed_tablet_can_login_by_trusted_ip_without_setup_code(): void
    {
        config()->set('device.allow_client_supplied_ip', true);
        config()->set('device.allowed_private_subnets', '192.168.100.0/24');

        $device = Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.162',
            'security_code' => null,
            'security_code_lookup' => null,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
        ])->getJson('/api/devices/login?ip_address=192.168.100.162');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('device.id', $device->id)
            ->assertJsonPath('ip_used', '192.168.100.162');
    }

    public function test_host_header_spoofing_cannot_authenticate_a_claimed_device(): void
    {
        Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.7',
            'security_code' => null,
            'security_code_lookup' => null,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '172.18.0.1',
            'SERVER_NAME' => '192.168.100.7',
            'SERVER_PORT' => '4443',
            'HTTP_HOST' => '192.168.100.7:4443',
            'HTTP_X_FORWARDED_HOST' => '192.168.100.7',
        ])->withHeaders([
            'Host' => '192.168.100.7:4443',
            'X-Forwarded-Host' => '192.168.100.7',
        ])->getJson('/api/devices/login');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Device not found')
            ->assertJsonPath('ip_address', '172.18.0.1');
    }

    public function test_device_ip_endpoint_reports_private_host_device_ip_instead_of_proxy_bridge_ip(): void
    {
        Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.7',
            'security_code' => null,
            'security_code_lookup' => null,
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '172.18.0.1',
            'SERVER_NAME' => '192.168.100.7',
            'SERVER_PORT' => '4443',
            'HTTP_HOST' => '192.168.100.7:4443',
            'HTTP_X_FORWARDED_HOST' => '192.168.100.7',
        ])->withHeaders([
            'Host' => '192.168.100.7:4443',
            'X-Forwarded-Host' => '192.168.100.7',
        ])->getJson('/api/device/ip');

        $response->assertStatus(200)
            ->assertJsonPath('ip', '192.168.100.7')
            ->assertJsonPath('request_ip', '172.18.0.1');
    }

    public function test_unclaimed_tablet_cannot_login_by_trusted_ip_without_setup_code(): void
    {
        config()->set('device.allow_client_supplied_ip', true);
        config()->set('device.allowed_private_subnets', '192.168.100.0/24');

        $device = Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.164',
            'security_code' => Hash::make('123456'),
            'security_code_lookup' => DeviceSecurityCode::lookupHash('123456'),
            'security_code_generated_at' => now(),
        ]);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
        ])->getJson('/api/devices/login?ip_address=192.168.100.164');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Device not yet registered with security code')
            ->assertJsonPath('device_id', $device->id);
    }

    public function test_login_rejects_invalid_global_passcode(): void
    {
        Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.163',
        ]);

        $response = $this->getJson('/api/devices/login?ip_address=192.168.100.163&passcode=999999');

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid passcode');
    }
}
