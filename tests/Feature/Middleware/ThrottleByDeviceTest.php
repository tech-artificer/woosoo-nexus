<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

class ThrottleByDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('device:1');
        RateLimiter::clear('device:2');
    }

    /** @test */
    public function it_rate_limits_by_device_id_for_authenticated_requests()
    {
        $device = Device::factory()->create();
        $token = $device->createToken('test-device')->plainTextToken;

        // Make 100 requests (the limit for create-order endpoint)
        for ($i = 0; $i < 100; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'subtotal' => 100,
                'tax' => 10,
                'discount' => 0,
                'total_amount' => 110,
                'items' => [
                    [
                        'menu_id' => 1,
                        'name' => 'Test Item',
                        'quantity' => 1,
                        'price' => 100,
                        'subtotal' => 100,
                    ],
                ],
            ]);

            // First 100 should not hit rate limit
            if ($i < 100) {
                $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate limited");
            }
        }

        // 101st request should be rate limited
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/devices/create-order', [
            'guest_count' => 2,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total_amount' => 110,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 100,
                    'subtotal' => 100,
                ],
            ],
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['success', 'message', 'retry_after']);
    }

    /** @test */
    public function it_prevents_ip_spoofing_bypass_via_x_forwarded_for()
    {
        $device = Device::factory()->create();
        $token = $device->createToken('test-device')->plainTextToken;

        // Exhaust rate limit with legitimate requests
        for ($i = 0; $i < 100; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'subtotal' => 100,
                'tax' => 10,
                'discount' => 0,
                'total_amount' => 110,
                'items' => [
                    [
                        'menu_id' => 1,
                        'name' => 'Test Item',
                        'quantity' => 1,
                        'price' => 100,
                        'subtotal' => 100,
                    ],
                ],
            ]);
        }

        // Attempt to bypass rate limit by spoofing IP via X-Forwarded-For
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Forwarded-For' => '192.168.1.99',  // Spoofed IP
        ])->postJson('/api/devices/create-order', [
            'guest_count' => 2,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total_amount' => 110,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 100,
                    'subtotal' => 100,
                ],
            ],
        ]);

        // Should STILL be rate limited (bypass prevented)
        $response->assertStatus(429);
    }

    /** @test */
    public function it_uses_fingerprint_for_unauthenticated_requests()
    {
        // Make 10 registration requests (the limit for /devices/register)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/devices/register', [
                'registration_code' => 'TEST-CODE-' . $i,
                'name' => 'Test Device ' . $i,
            ]);

            // Requests may fail due to invalid registration code, but shouldn't be rate limited
            if ($i < 10) {
                $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate limited");
            }
        }

        // 11th request should be rate limited
        $response = $this->postJson('/api/devices/register', [
            'registration_code' => 'TEST-CODE-11',
            'name' => 'Test Device 11',
        ]);

        $response->assertStatus(429);
    }

    /** @test */
    public function it_isolates_rate_limits_between_different_devices()
    {
        $device1 = Device::factory()->create();
        $device2 = Device::factory()->create();

        $token1 = $device1->createToken('device1')->plainTextToken;
        $token2 = $device2->createToken('device2')->plainTextToken;

        // Exhaust rate limit for device 1
        for ($i = 0; $i < 100; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer ' . $token1,
            ])->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'subtotal' => 100,
                'tax' => 10,
                'discount' => 0,
                'total_amount' => 110,
                'items' => [
                    [
                        'menu_id' => 1,
                        'name' => 'Test Item',
                        'quantity' => 1,
                        'price' => 100,
                        'subtotal' => 100,
                    ],
                ],
            ]);
        }

        // Device 1 should be rate limited
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
        ])->postJson('/api/devices/create-order', [
            'guest_count' => 2,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total_amount' => 110,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 100,
                    'subtotal' => 100,
                ],
            ],
        ]);
        $response1->assertStatus(429);

        // Device 2 should NOT be rate limited (isolated limit)
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->postJson('/api/devices/create-order', [
            'guest_count' => 2,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total_amount' => 110,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 100,
                    'subtotal' => 100,
                ],
            ],
        ]);
        $this->assertNotEquals(429, $response2->status(), 'Device 2 should have independent rate limit');
    }

    /** @test */
    public function it_includes_rate_limit_headers_in_response()
    {
        $device = Device::factory()->create();
        $token = $device->createToken('test-device')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/devices/create-order', [
            'guest_count' => 2,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total_amount' => 110,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 100,
                    'subtotal' => 100,
                ],
            ],
        ]);

        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit'),
            'Response should include X-RateLimit-Limit header'
        );
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Remaining'),
            'Response should include X-RateLimit-Remaining header'
        );
    }
}
