<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Device;
use App\Support\DeviceSecurityCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Feature tests for DeviceAuthApiController::authenticate().
 *
 * NEX-CASE-004: POST /api/devices/login returned HTTP 500 when the POS
 * database was unavailable because $device->table()->first() was uncaught.
 * These tests verify the resilient fix: auth succeeds and table is null on
 * POS failure, and auth succeeds with table data when POS is healthy.
 */
class DeviceAuthApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Device model boot enforces exactly one local branch.
        Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);

        // Configure trusted-IP login so tests can supply an IP directly.
        config()->set('device.allow_client_supplied_ip', true);
        config()->set('device.allowed_private_subnets', '192.168.100.0/24');
    }

    // -------------------------------------------------------------------------
    // Happy path — POS up
    // -------------------------------------------------------------------------

    /**
     * When POS is healthy and the device has a table_id that matches a record
     * in the POS tables table, authenticate() returns 200 and table.id is set.
     */
    public function test_authenticate_returns_200_with_table_when_pos_is_up(): void
    {
        // Insert a row into the in-memory POS tables table (created by TestCase::setUp).
        DB::connection('pos')->table('tables')->insert(['id' => 42, 'name' => 'Table 5']);

        $device = Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.10',
            'table_id' => 42,
            'security_code' => null,
            'security_code_lookup' => null,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/devices/login', ['ip_address' => '192.168.100.10']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('table.id', 42)
            ->assertJsonPath('table.name', 'Table 5');

        // Token must be issued.
        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * When the device has no table assigned (table_id = null), authenticate()
     * returns 200 and table is null — no POS query should cause a 500.
     */
    public function test_authenticate_returns_200_with_null_table_when_device_has_no_table(): void
    {
        $device = Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.11',
            'table_id' => null,
            'security_code' => null,
            'security_code_lookup' => null,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/devices/login', ['ip_address' => '192.168.100.11']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('table', null);

        $this->assertNotEmpty($response->json('token'));
    }

    // -------------------------------------------------------------------------
    // NEX-CASE-004 core fix — POS down
    // -------------------------------------------------------------------------

    /**
     * When the POS database is unreachable, authenticate() MUST still return
     * HTTP 200 with table = null.  Before the fix, an uncaught QueryException
     * from $device->table()->first() caused a 500 here.
     *
     * The test simulates POS failure by reconfiguring the 'pos' connection to
     * point at a nonexistent file path, forcing a PDO/QueryException.
     */
    public function test_authenticate_returns_200_with_null_table_when_pos_is_down(): void
    {
        $device = Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.12',
            'table_id' => 99, // Has a table_id so the POS query IS attempted.
            'security_code' => null,
            'security_code_lookup' => null,
        ]);

        // Break the POS connection AFTER device creation (device uses the default DB).
        config(['database.connections.pos' => [
            'driver' => 'sqlite',
            'database' => '/nonexistent/__broken_pos_db__.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('pos');

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/devices/login', ['ip_address' => '192.168.100.12']);

        // Auth must succeed — 500 is the bug; 200 with null table is the fix.
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('table', null);

        // Token must still be issued — auth flow completed before the POS call.
        $token = $response->json('token');
        $this->assertNotEmpty($token, 'A valid token must be issued even when POS is down.');

        // Verify the token is valid in the DB.
        [$tokenId] = explode('|', $token, 2);
        $pat = PersonalAccessToken::find((int) $tokenId);
        $this->assertNotNull($pat, 'The issued token must persist in personal_access_tokens.');
        $this->assertSame($device->id, $pat->tokenable_id);
    }

    // -------------------------------------------------------------------------
    // Error paths
    // -------------------------------------------------------------------------

    /**
     * When no active device is found for the given IP, authenticate() returns 404.
     */
    public function test_authenticate_returns_404_when_device_not_found(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/devices/login', ['ip_address' => '192.168.100.20']);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Device not found');
    }

    /**
     * When a device is found by IP but has not been claimed with a security code
     * (security_code is still set), authenticate() returns 403.
     */
    public function test_authenticate_returns_403_when_device_is_not_yet_registered(): void
    {
        Device::factory()->create(array_merge(
            [
                'is_active' => true,
                'ip_address' => '192.168.100.30',
            ],
            DeviceSecurityCode::attributesFor('123456')
        ));

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/devices/login', ['ip_address' => '192.168.100.30']);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Device not yet registered with security code');
    }

    // -------------------------------------------------------------------------
    // Token lifecycle assertions
    // -------------------------------------------------------------------------

    /**
     * authenticate() prunes expired tokens for the device and issues exactly
     * one fresh token with a ~30-day expiry on each login.
     */
    public function test_authenticate_prunes_expired_tokens_and_issues_fresh_30_day_token(): void
    {
        $device = Device::factory()->create([
            'is_active' => true,
            'ip_address' => '192.168.100.40',
            'security_code' => null,
            'security_code_lookup' => null,
        ]);

        // Seed an expired token — it must be deleted on login.
        $device->createToken('device-auth', expiresAt: now()->subDays(5));

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/devices/login', ['ip_address' => '192.168.100.40']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $tokens = PersonalAccessToken::where('tokenable_type', Device::class)
            ->where('tokenable_id', $device->id)
            ->get();

        // Only the newly issued token should remain.
        $this->assertCount(1, $tokens, 'Expired tokens must be pruned; only the new token should remain.');
        $this->assertTrue(
            $tokens->first()->expires_at->between(now()->addDays(29), now()->addDays(31)),
            'New token expiry should be approximately 30 days from now.'
        );
    }
}
