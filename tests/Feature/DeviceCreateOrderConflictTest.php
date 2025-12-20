<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Device;
use App\Models\Branch;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Enums\OrderStatus;

class DeviceCreateOrderConflictTest extends TestCase
{
    use RefreshDatabase;

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
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/devices/create-order', $payload);

        $response->assertStatus(409);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('existing order', strtolower($response->json('message')));
    }
}
