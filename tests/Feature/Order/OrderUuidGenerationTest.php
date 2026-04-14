<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderUuidGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_order_generates_uuid_and_display_order_number_suffix(): void
    {
        $branch = Branch::create([
            'name' => 'Main',
            'location' => 'HQ',
        ]);

        $device = Device::create([
            'branch_id' => $branch->id,
            'name' => 'UUID Device',
            'ip_address' => '192.168.100.77',
            'is_active' => true,
            'table_id' => 1,
        ]);

        $order = DeviceOrder::create([
            'branch_id' => $branch->id,
            'device_id' => $device->id,
            'table_id' => 1,
            'order_id' => 123456,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'status' => OrderStatus::PENDING->value,
            'sub_total' => 100.00,
            'subtotal' => 100.00,
            'tax' => 12.00,
            'discount' => 0.00,
            'total' => 112.00,
            'guest_count' => 2,
        ]);

        $this->assertNotNull($order->order_uuid);
        $this->assertTrue(Str::isUuid($order->order_uuid));
        $this->assertNotNull($order->order_number);
        $this->assertStringStartsWith('ORD-' . now()->format('Ymd') . '-', $order->order_number);
        $this->assertStringEndsWith(strtoupper(substr($order->order_uuid, -6)), $order->order_number);
    }
}
