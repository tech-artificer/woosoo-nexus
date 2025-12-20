<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Branch;
use App\Enums\OrderStatus;
use Laravel\Sanctum\Sanctum;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_auth_is_required_for_v1_orders_index()
    {
        $resp = $this->get('/api/v1/orders');
        $this->assertTrue(in_array($resp->getStatusCode(), [401,403,302]));
    }

    public function test_device_can_filter_orders_and_receive_meta_counts()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Kitchen Station 1',
            'ip_address' => '127.0.0.10',
            'is_active' => true,
            'table_id' => 1,
            'branch_id' => 1,
        ]);

        $sessionId = $this->createTestSession();

        // Seed orders across statuses
        DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'branch_id' => $device->branch_id,
            'session_id' => $sessionId,
            'order_id' => 5001,
            'order_number' => 'ORD-5001',
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'branch_id' => $device->branch_id,
            'session_id' => $sessionId,
            'order_id' => 5002,
            'order_number' => 'ORD-5002',
            'status' => OrderStatus::IN_PROGRESS->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        // Authenticate as device via Sanctum device guard
        Sanctum::actingAs($device, [], 'device');

        $resp = $this->get('/api/v1/orders?status=pending,in_progress&branch=1&station=1');
        $resp->assertStatus(200);
        $json = $resp->json();
        $this->assertTrue($json['success'] ?? false);
        $this->assertIsArray($json['data']);
        $this->assertIsArray($json['meta']);
        $this->assertIsArray($json['meta']['counts']);
        foreach (['pending','in_progress','ready','completed','cancelled'] as $k) {
            $this->assertArrayHasKey($k, $json['meta']['counts']);
        }
    }

    public function test_update_status_endpoint()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);
        $device = Device::create([
            'name' => 'Kitchen Station 2',
            'ip_address' => '127.0.0.11',
            'is_active' => true,
            'table_id' => 2,
            'branch_id' => 1,
        ]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'branch_id' => $device->branch_id,
            'session_id' => $sessionId,
            'order_id' => 6001,
            'order_number' => 'ORD-6001',
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        Sanctum::actingAs($device, [], 'device');

        $resp = $this->patch('/api/v1/orders/' . $order->id . '/status', ['status' => OrderStatus::CONFIRMED->value]);
        $resp->assertStatus(200);
        $order->refresh();
        $this->assertEquals(OrderStatus::CONFIRMED, $order->status);
    }

    public function test_bulk_status_endpoint()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);
        $device = Device::create([
            'name' => 'Kitchen Station 3',
            'ip_address' => '127.0.0.12',
            'is_active' => true,
            'table_id' => 3,
            'branch_id' => 1,
        ]);

        $sessionId = $this->createTestSession();

        $o1 = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'branch_id' => $device->branch_id,
            'session_id' => $sessionId,
            'order_id' => 7001,
            'order_number' => 'ORD-7001',
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);
        $o2 = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'branch_id' => $device->branch_id,
            'session_id' => $sessionId,
            'order_id' => 7002,
            'order_number' => 'ORD-7002',
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        Sanctum::actingAs($device, [], 'device');

        $resp = $this->post('/api/v1/orders/status/bulk', [
            'order_ids' => [$o1->id, $o2->id],
            'status' => OrderStatus::CONFIRMED->value,
        ]);
        $resp->assertStatus(200);

        $o1->refresh();
        $o2->refresh();
        $this->assertEquals(OrderStatus::CONFIRMED, $o1->status);
        $this->assertEquals(OrderStatus::CONFIRMED, $o2->status);
    }
}
