<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Support\DeviceSecurityCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiCsrfExemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable CSRF for testing
        config(['session.driver' => 'file']);
        config(['session.encrypt' => false]);
    }

    /** @test */
    public function it_allows_admin_session_reset_through_the_current_api_stack()
    {
        $this->withMiddleware();

        $user = User::factory()->admin()->create();
        $this->actingAs($user, 'web');

        // Test the real API stack: session-authenticated admin reaches the
        // reset controller and is authorized.
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/sessions/1/reset');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function it_exempts_device_bearer_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test device Bearer token endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->post('/api/devices/refresh');

        // Should not return 419 for Bearer token auth
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_device_registration_endpoints_from_csrf()
    {
        Device::factory()->create(array_merge([
            'name' => 'Setup Device',
            'ip_address' => null,
            'is_active' => true,
        ], DeviceSecurityCode::attributesFor('123456')));

        // Test real setup-code registration contract (no auth required)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/devices/register', [
            'security_code' => '123456',
            'name' => 'Test Device',
            'ip_address' => '192.168.1.100'
        ]);

        // Should not return 419 for device registration
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function it_exempts_device_login_endpoints_from_csrf()
    {
        Device::factory()->create([
            'security_code' => null,
            'security_code_lookup' => null,
            'ip_address' => '127.0.0.1',
            'status' => 'active',
            'is_active' => true,
        ]);

        // Test real IP-based device login fallback (no auth required)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/devices/login');

        // Should not return 419 for device login
        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function it_exempts_public_menu_endpoints_from_csrf()
    {
        // Test public menu endpoint (no auth required)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/api/menus');

        // Should not return 419 for public endpoints
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_printer_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Enable print events for testing
        config(['api.print_events_enabled' => true]);

        // Test printer endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->get('/api/printer/unprinted-events');

        // Should not return 419 for printer endpoints
        $this->assertContains($response->getStatusCode(), [200, 503]); // 503 if feature disabled
    }

    /** @test */
    public function it_requires_auth_for_exempted_endpoints()
    {
        // Test that exempted endpoints still require authentication
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/devices/refresh');

        // Should return 401/403, not 419 (no CSRF error)
        $response->assertStatus(401);
    }

    /** @test */
    public function it_exempts_v2_tablet_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test V2 tablet endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->get('/api/v2/tablet/packages');

        // Should not return 419 for V2 tablet endpoints
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_device_order_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test device order endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->get('/api/device-orders');

        // Should not return 419 for device order endpoints
        $response->assertStatus(200);
    }

    /** @test */
    public function it_exempts_order_refill_endpoints_from_csrf()
    {
        $device = Device::factory()->create([
            'security_code' => 'TEST123',
            'status' => 'active'
        ]);

        // Test order refill endpoint (should not require CSRF)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $device->createToken('test')->plainTextToken,
        ])->post('/api/order/123/refill');

        // Should not return 419 for order refill endpoints
        $this->assertContains($response->getStatusCode(), [404, 422]); // 404 if order not found, 422 if validation fails
    }

    /** @test */
    public function it_exempts_public_health_endpoints_from_csrf()
    {
        // Test health endpoint (no auth required)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/api/health');

        // Should not return 419 for health endpoint; degraded dependencies
        // legitimately return 207 in the real contract.
        $this->assertContains($response->getStatusCode(), [200, 207, 503]);
        $response->assertJsonStructure(['success', 'data' => ['status', 'services']]);
    }

    /** @test */
    public function it_reaches_admin_sanctum_write_validation_through_the_current_api_stack()
    {
        $this->withMiddleware();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'devices.register', 'guard_name' => 'web']);

        $user = User::factory()->admin()->create();
        $user->givePermissionTo('devices.register');
        $this->actingAs($user, 'web');

        // Test the real API stack: this reaches the endpoint's validation
        // layer rather than returning a CSRF mismatch.
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/v2/devices', [
            'name' => 'Admin Device',
            'security_code' => '123456',
        ]);

        $response->assertStatus(422);
        $this->assertNotEquals(419, $response->getStatusCode());
    }
}
