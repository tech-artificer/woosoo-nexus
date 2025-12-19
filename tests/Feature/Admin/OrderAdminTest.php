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

        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
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

        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $resp1 = $this->actingAs($admin)->get('/orders?search=SEARCH');
        $resp1->assertStatus(200);
        $resp1->assertSee($orderA->order_number);

        $resp2 = $this->actingAs($admin)->get('/orders?search=SearchDeviceA');
        $resp2->assertStatus(200);
        $resp2->assertSee($orderA->order_number);
    }

    public function test_admin_multi_select_statuses_filter_persists_in_url()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Kitchen A',
            'ip_address' => '127.0.0.3',
            'is_active' => true,
            'table_id' => 3,
        ]);

        $order1 = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 3101,
            'order_number' => 'ORD-MULTI-3101',
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        $order2 = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 3102,
            'order_number' => 'ORD-MULTI-3102',
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

        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $resp = $this->actingAs($admin)->get('/orders?status=pending,confirmed');
        $resp->assertStatus(200);
        $resp->assertSee($order1->order_number);
        $resp->assertSee($order2->order_number);
    }

    public function test_admin_date_presets_filter_range()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Kitchen B',
            'ip_address' => '127.0.0.4',
            'is_active' => true,
            'table_id' => 4,
        ]);

        // Create two orders on different dates
        $older = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 3201,
            'order_number' => 'ORD-DATE-3201',
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $recent = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 3202,
            'order_number' => 'ORD-DATE-3202',
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        /** @var \Illuminate\Contracts\Auth\Authenticatable $admin */
        $from = now()->subDays(3)->toDateString();
        $to = now()->toDateString();
        $resp = $this->actingAs($admin)->get("/orders?date_from={$from}&date_to={$to}");
        $resp->assertStatus(200);
        $resp->assertSee($recent->order_number);
        $resp->assertDontSee($older->order_number);
    }
}
