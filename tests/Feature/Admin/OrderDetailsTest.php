<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\PrintEvent;
use App\Models\User;
use App\Enums\OrderStatus;

class OrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_order_details_with_refill_history()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Order Details Device',
            'ip_address' => '127.0.0.9',
            'is_active' => true,
            'table_id' => 9,
        ]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'order_id' => 9001,
            'order_number' => 'ORD-DETAILS-9001',
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 100.00,
            'tax' => 10.00,
            'discount' => 0.00,
            'total' => 110.00,
            'guest_count' => 2,
        ]);

        DeviceOrderItems::create([
            'order_id' => $order->id,
            'menu_id' => 1,
            'ordered_menu_id' => 1,
            'quantity' => 2,
            'price' => 50.00,
            'subtotal' => 100.00,
            'tax' => 10.00,
            'total' => 110.00,
            'notes' => null,
            'is_refill' => false,
        ]);

        DeviceOrderItems::create([
            'order_id' => $order->id,
            'menu_id' => 2,
            'ordered_menu_id' => 999,
            'quantity' => 1,
            'price' => 0.00,
            'subtotal' => 0.00,
            'tax' => 0.00,
            'total' => 0.00,
            'notes' => 'Refill requested',
            'is_refill' => true,
        ]);

        PrintEvent::create([
            'device_order_id' => $order->id,
            'event_type' => 'REFILL',
            'meta' => [
                'items' => [
                    ['name' => 'Refill Beef', 'quantity' => 1],
                ],
            ],
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $response = $this->actingAs($admin)->getJson("/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'order' => [
                'id',
                'order_id',
                'order_number',
                'device_id',
                'status',
                'created_at',
                'items',
                'print_events',
            ],
        ]);
        $response->assertJsonFragment(['event_type' => 'REFILL']);
        $response->assertJsonFragment(['is_refill' => true]);
    }
}
