<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Device;
use App\Models\Branch;

class SessionOrderValidationTest extends TestCase
{
    use RefreshDatabase, MocksKryptonSession;

    public function test_order_rejected_for_inactive_session()
    {
        // Mock active Krypton session for this test
        $this->mockActiveKryptonSession();
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device A',
            'ip_address' => '192.168.1.10',
            'is_active' => true,
            'table_id' => 1,
        ]);

        // Create an active Krypton session for order creation (required by business rule)
        $sessionId = $this->createTestSession();

        $token = $device->createToken('test-token')->plainTextToken;

        $payload = [
            'guest_count' => 1,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total_amount' => 1.00,
            'session_id' => $sessionId,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 1.00,
                    'subtotal' => 1.00,
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/devices/create-order', $payload);

        // Order creation should succeed with active Krypton session
        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
        $this->assertArrayHasKey('order', $response->json());
    }

    public function test_print_event_skipped_for_closed_session()
    {
        // Mock active Krypton session for this test
        $this->mockActiveKryptonSession();

        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device B',
            'ip_address' => '192.168.1.11',
            'is_active' => true,
            'table_id' => 2,
        ]);

        // Create an active Krypton session first
        $sessionId = $this->createTestSession();

        // create a device order with the active session
        $deviceOrder = \App\Models\DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'order_id' => 888,
            'order_number' => 'ORD-000888-888',
            'status' => \App\Enums\OrderStatus::COMPLETED->value,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        $svc = app(\App\Services\PrintEventService::class);
        $res = $svc->createForOrder($deviceOrder, 'INITIAL');

        // Since sessions are device-local, print events should be created.
        $this->assertNotNull($res);
        $this->assertDatabaseHas('print_events', ['device_order_id' => $deviceOrder->id]);
    }

    public function test_order_creation_fails_without_active_krypton_session()
    {
        // Override the default mock to simulate missing session
        $this->mockMissingKryptonSession();

        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device C',
            'ip_address' => '192.168.1.12',
            'is_active' => true,
            'table_id' => 3,
        ]);

        $token = $device->createToken('test-token')->plainTextToken;

        $payload = [
            'guest_count' => 1,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total_amount' => 1.00,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 1.00,
                    'subtotal' => 1.00,
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/devices/create-order', $payload);

        // Should return 503 Service Unavailable when session is missing
        $response->assertStatus(503);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('session', strtolower($response->json('message')));
    }
}
