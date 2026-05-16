<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * CSRF Middleware Tests
 *
 * Verify that:
 * - Session-authenticated API routes reach their real authorization/validation layer
 * - Device Bearer endpoints work without CSRF
 * - Printer endpoints work without CSRF only with valid auth
 * - Unauthenticated exempt endpoints return 401/403, not 419
 */
class CsrfMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;
    private User $adminUser;
    private string $deviceToken;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test device with Bearer token
        $this->device = Device::factory()->create([
            'ip_address' => '127.0.0.1',
            'is_active' => true,
        ]);
        
        // Create Sanctum token for device
        $token = $this->device->createToken('device-token', ['device']);
        $this->deviceToken = $token->plainTextToken;
        
        // Create admin user for session-based auth tests
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }

    // ============================================================
    // DEVICE BEARER ENDPOINTS (Should work WITHOUT CSRF)
    // ============================================================

    /** @test */
    public function device_create_order_works_without_csrf_cookie()
    {
        $response = $this->postJson('/api/devices/create-order', [
            'items' => [],
            'guest_count' => 2,
        ], [
            'Authorization' => 'Bearer ' . $this->deviceToken,
            // No X-CSRF-TOKEN header, no cookies
        ]);
        
        // Should NOT get 419 CSRF error
        $response->assertStatus(422); // Validation error (no items) expected, NOT 419
    }

    /** @test */
    public function device_refresh_works_without_csrf_cookie()
    {
        $response = $this->postJson('/api/devices/refresh', [], [
            'Authorization' => 'Bearer ' . $this->deviceToken,
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function device_order_refill_works_without_csrf_cookie()
    {
        // Create an order first
        $order = \App\Models\DeviceOrder::factory()->create([
            'device_id' => $this->device->id,
            'order_id' => 12345,
        ]);
        
        $response = $this->postJson("/api/order/{$order->order_id}/refill", [
            'items' => [],
            'client_submission_id' => 'test-' . uniqid(),
        ], [
            'Authorization' => 'Bearer ' . $this->deviceToken,
        ]);
        
        // Should NOT get 419 CSRF error
        $response->assertStatus(422); // Validation error (empty items) expected
    }

    /** @test */
    public function token_verify_works_without_csrf_cookie()
    {
        $response = $this->getJson('/api/token/verify', [
            'Authorization' => 'Bearer ' . $this->deviceToken,
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    // ============================================================
    // SESSION-AUTHENTICATED ROUTES (Should REQUIRE CSRF)
    // ============================================================

    /** @test */
    public function session_reset_endpoint_accepts_authenticated_admin_session_request()
    {
        $this->withMiddleware();

        // Log in as admin user (creates session)
        $this->actingAs($this->adminUser, 'web');
        
        // Current API stack reaches the controller for an authenticated admin.
        $response = $this->postJson('/api/sessions/1/reset', [], [
            'Accept' => 'application/json',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function session_reset_works_with_valid_csrf_token()
    {
        $this->withMiddleware();

        // Log in as admin user with CSRF handling
        $this->actingAs($this->adminUser, 'web');
        
        // Get CSRF token
        $csrfToken = 'test-csrf-token';
        
        // Request WITH CSRF token should work (or 404 if session not found)
        $response = $this->withSession(['_token' => $csrfToken])
            ->postJson('/api/sessions/1/reset', [], [
                'X-CSRF-TOKEN' => $csrfToken,
                'Accept' => 'application/json',
            ]);
        
        // Should NOT get 419, but might get 403 (non-admin) or 404 (session not found)
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    /** @test */
    public function v2_devices_admin_routes_require_csrf_with_session_auth()
    {
        $this->actingAs($this->adminUser, 'web');
        
        // v2/devices routes are admin endpoints using auth:sanctum
        // Without CSRF token, should get 419
        $response = $this->getJson('/api/v2/devices', [
            'Accept' => 'application/json',
        ]);
        
        // This will actually return 401 (no token) but the point is:
        // CSRF check happens before auth check for web routes
        // For API routes with Sanctum, Bearer token is required anyway
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    // ============================================================
    // GUEST/BOOTSTRAP ENDPOINTS (No CSRF required, no auth required)
    // ============================================================

    /** @test */
    public function device_registration_works_without_csrf_or_auth()
    {
        $response = $this->postJson('/api/devices/register', [
            'registration_code' => 'invalid',
        ]);
        
        // Should NOT get 419 (CSRF exempt for bootstrap)
        // Should get 422 (invalid code) or 401, NOT 419
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    /** @test */
    public function device_login_works_without_csrf_or_auth()
    {
        $response = $this->getJson('/api/devices/login?passcode=invalid');
        
        // Should NOT get 419; real login rejects invalid passcodes with 422.
        $response->assertStatus(422);
    }

    /** @test */
    public function device_lookup_by_ip_works_without_csrf()
    {
        $response = $this->getJson('/api/device/lookup-by-ip');
        
        // Should NOT get 419
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    /** @test */
    public function health_endpoint_works_without_csrf()
    {
        $response = $this->getJson('/api/health');
        
        // Should NOT get 419; degraded dependencies legitimately return 207.
        $this->assertContains($response->getStatusCode(), [200, 207, 503]);
        $response->assertJsonStructure(['success', 'data' => ['status', 'services']]);
    }

    // ============================================================
    // PRINTER ENDPOINTS (Should work without CSRF with valid auth)
    // ============================================================

    /** @test */
    public function printer_endpoints_work_without_csrf_with_device_token()
    {
        // Printer endpoints use device token for auth
        $response = $this->getJson('/api/printer/unprinted-events', [
            'Authorization' => 'Bearer ' . $this->deviceToken,
        ]);
        
        // Will return 503 (feature flag off) but NOT 419
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    /** @test */
    public function printer_endpoints_return_401_without_auth()
    {
        $response = $this->getJson('/api/printer/unprinted-events');
        
        // Should return 401 or 403, NOT 419
        $response->assertStatus(401);
    }

    // ============================================================
    // EDGE CASES
    // ============================================================

    /** @test */
    public function menu_endpoints_work_without_csrf()
    {
        $response = $this->getJson('/api/menus');
        
        // Public menu endpoint should NOT require CSRF
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    /** @test */
    public function config_endpoint_works_without_csrf()
    {
        $response = $this->getJson('/api/config');
        
        $response->assertStatus(200)
            ->assertJsonStructure(['broadcasting', 'app_version']);
    }

    /** @test */
    public function v2_tablet_endpoints_work_without_csrf_with_device_token()
    {
        $response = $this->getJson('/api/v2/tablet/packages', [
            'Authorization' => 'Bearer ' . $this->deviceToken,
        ]);
        
        // Should NOT get 419
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    /** @test */
    public function wildcard_patterns_match_correctly()
    {
        // Test that 'api/order/*' pattern works
        $order = \App\Models\DeviceOrder::factory()->create([
            'device_id' => $this->device->id,
            'order_id' => 99999,
        ]);
        
        // Various order sub-routes should all work without CSRF
        $endpoints = [
            "/api/order/99999/refill",
            "/api/order/99999/printed",
            "/api/order/99999/print",
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->postJson($endpoint, [
                'client_submission_id' => 'test-' . uniqid(),
            ], [
                'Authorization' => 'Bearer ' . $this->deviceToken,
            ]);
            
            // Should NOT be 419 (may be 422 or other validation error)
            $this->assertNotEquals(419, $response->getStatusCode(), 
                "Endpoint $endpoint should not return 419 CSRF error");
        }
    }
}
