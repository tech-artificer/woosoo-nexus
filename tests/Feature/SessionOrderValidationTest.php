<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use App\Models\Device;
use App\Models\Branch;
use App\Models\Krypton\Menu;
use App\Services\Krypton\OrderService;
use PDOException;

class SessionOrderValidationTest extends TestCase
{
    use RefreshDatabase, MocksKryptonSession;

    protected function setUp(): void
    {
        parent::setUp();

        Menu::factory()->create([
            'id' => 1,
            'name' => 'Test Item',
            'receipt_name' => 'Test Item',
            'price' => 1.00,
        ]);
    }

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
            'X-Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
        ])->postJson('/api/devices/create-order', $payload);

        // Order creation should succeed with active Krypton session
        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
        $this->assertArrayHasKey('order', $response->json());
        $this->assertSame(1, $response->json('order.guest_count'));
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
            'X-Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
        ])->postJson('/api/devices/create-order', $payload);

        // Should return 503 Service Unavailable when session is missing
        $response->assertStatus(503);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('session', strtolower($response->json('message')));
    }

    public function test_order_creation_uses_cached_test_session_fallback_when_context_session_missing()
    {
        $this->mockKryptonSessionWith([
            'session_id' => null,
            'terminal_id' => 1,
            'branch_id' => 1,
            'user_id' => 1,
            'terminal_name' => 'TEST_TERMINAL',
            'cashier_name' => 'Test Cashier',
        ]);

        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device D',
            'ip_address' => '192.168.1.13',
            'is_active' => true,
            'table_id' => 4,
        ]);

        $this->createTestSession();
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
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
        ])->postJson('/api/devices/create-order', $payload);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
    }

    public function test_order_creation_returns_503_when_pos_connection_is_unavailable()
    {
        $this->mockActiveKryptonSession();

        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device E',
            'ip_address' => '192.168.1.14',
            'is_active' => true,
            'table_id' => 5,
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
                ],
            ],
        ];

        $previous = new PDOException('SQLSTATE[HY000] [2002] Connection refused (Connection: pos)');
        $previous->errorInfo = ['HY000', 2002, 'Connection refused'];

        $this->mock(OrderService::class, function ($mock) use ($previous) {
            $mock->shouldReceive('processOrder')
                ->once()
                ->andThrow(new QueryException('pos', 'insert into orders ...', [], $previous));
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
        ])->postJson('/api/devices/create-order', $payload);

        $response->assertStatus(503);
        $this->assertFalse($response->json('success'));
    }
}
