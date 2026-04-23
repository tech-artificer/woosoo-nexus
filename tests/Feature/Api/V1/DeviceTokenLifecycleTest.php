<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Device;
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
            'security_code' => Hash::make('123456'),
            'is_active'     => true,
        ]);

        $response = $this->postJson('/api/devices/register', [
            'name'          => 'Test Tablet',
            'security_code' => '123456',
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
     * Test: Re-register cleans up expired tokens before issuing a new one
     * Purpose: Verify expired tokens are purged on each register call
     */
    public function test_register_purges_expired_tokens_on_reregister(): void
    {
        $device = Device::factory()->create([
            'security_code' => Hash::make('123456'),
            'is_active'     => true,
        ]);

        // Seed an already-expired token
        $device->createToken('old-expired', expiresAt: now()->subDay());

        $this->assertCount(1, $device->tokens);

        // Re-register
        $response = $this->postJson('/api/devices/register', [
            'name'          => 'Test Tablet',
            'security_code' => '123456',
        ]);

        $response->assertStatus(200);

        // Expired token should be gone; only the new one remains
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
}
