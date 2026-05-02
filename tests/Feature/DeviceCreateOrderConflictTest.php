<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCreated;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Services\Krypton\OrderService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;

class DeviceCreateOrderConflictTest extends TestCase
{
    use MocksKryptonSession, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock active Krypton session for all tests
        $this->mockActiveKryptonSession();
    }

    public function test_device_cannot_create_order_when_existing_pending_or_confirmed_exists()
    {
        // Ensure a Branch exists (Device and DeviceOrder boot expect one)
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device Conflict',
            'ip_address' => '192.168.100.5',
            'is_active' => true,
            'table_id' => 10,
        ]);

        // Create an active Krypton session for order creation
        $sessionId = $this->createTestSession();

        // Create an existing DeviceOrder with PENDING status for this device
        $deviceOrder = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'order_id' => 12345,
            'order_number' => 'ORD-000001-12345',
            'status' => OrderStatus::PENDING->value,
            // items/meta moved to device_order_items and meta accessor
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        // Persist corresponding device order item
        DeviceOrderItems::create([
            'order_id' => $deviceOrder->id,
            'menu_id' => 1,
            'quantity' => 1,
            'price' => 1.00,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'total' => 1.00,
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

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Idempotency-Key' => Str::uuid()->toString(),
        ])->postJson('/api/devices/create-order', $payload);

        $response->assertStatus(409);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('existing order', strtolower($response->json('message')));
    }

    public function test_order_creation_succeeds_when_realtime_broadcast_is_unavailable(): void
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device Broadcast Failure',
            'ip_address' => '192.168.100.6',
            'is_active' => true,
            'table_id' => 10,
        ]);

        $sessionId = $this->createTestSession();

        Event::listen(OrderCreated::class, function (): void {
            throw new BroadcastException('Pusher error: connection refused');
        });

        $this->mock(OrderService::class, function ($mock) use ($device, $sessionId) {
            $mock->shouldReceive('processOrder')
                ->once()
                ->andReturnUsing(function () use ($device, $sessionId) {
                    $deviceOrder = DeviceOrder::create([
                        'device_id' => $device->id,
                        'table_id' => $device->table_id,
                        'terminal_session_id' => 1,
                        'session_id' => $sessionId,
                        'order_id' => 23456,
                        'order_number' => 'ORD-000001-23456',
                        'status' => OrderStatus::CONFIRMED->value,
                        'subtotal' => 1.00,
                        'tax' => 0.00,
                        'discount' => 0.00,
                        'total' => 1.00,
                        'guest_count' => 1,
                    ]);

                    DeviceOrderItems::create([
                        'order_id' => $deviceOrder->id,
                        'menu_id' => 1,
                        'quantity' => 1,
                        'price' => 1.00,
                        'subtotal' => 1.00,
                        'tax' => 0.00,
                        'total' => 1.00,
                    ]);

                    return $deviceOrder;
                });
        });

        $token = $device->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Idempotency-Key' => Str::uuid()->toString(),
        ])->postJson('/api/devices/create-order', [
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
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.order_id', 23456);
    }
}
