<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\User;
use App\Enums\OrderStatus;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_orders_by_status()
    {
        // Minimal setup
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Filter Device',
            'ip_address' => '127.0.0.1',
            'is_active' => true,
            'table_id' => 1,
        ]);

        // Create two orders with different statuses
        $orderConfirmed = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 2001,
            'order_number' => 'ORD-2001-2001',
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        $orderPending = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 2002,
            'order_number' => 'ORD-2002-2002',
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $response = $this->actingAs($admin)->get('/orders?status=confirmed');
        $response->assertStatus(200);

        // Confirm that confirmed order is present and pending one is not
        $response->assertSee($orderConfirmed->order_number);
        $response->assertDontSee($orderPending->order_number);
    }

    public function test_admin_can_search_orders_by_order_number_or_device()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $deviceA = Device::create([
            'name' => 'SearchDeviceA',
            'ip_address' => '127.0.0.2',
            'is_active' => true,
            'table_id' => 2,
        ]);

        $orderA = DeviceOrder::create([
            'device_id' => $deviceA->id,
            'table_id' => $deviceA->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 3001,
            'order_number' => 'ORD-SEARCH-3001',
            'status' => OrderStatus::IN_PROGRESS->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $resp1 = $this->actingAs($admin)->get('/orders?search=SEARCH');
        $resp1->assertStatus(200);
        $resp1->assertSee($orderA->order_number);

        $resp2 = $this->actingAs($admin)->get('/orders?search=SearchDeviceA');
        $resp2->assertStatus(200);
        $resp2->assertSee($orderA->order_number);
    }
}
