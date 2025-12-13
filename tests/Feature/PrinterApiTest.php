<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Device;
use App\Models\DeviceOrder;
use Illuminate\Support\Facades\Event;
use App\Events\PrintOrder;
use Laravel\Sanctum\Sanctum;

class PrinterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_printed_sets_flags_and_dispatches_events()
    {
        Event::fake([PrintOrder::class]);

        // Create branch and device directly
        $branch = \App\Models\Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);
        $device = Device::create(['name' => 'test-device', 'ip_address' => '192.168.1.100', 'branch_id' => $branch->id]);

        // token
        $token = $device->createToken('device-auth')->plainTextToken;

        // Create a device order
        $order = DeviceOrder::create([
            'order_id' => 999999,
            'device_id' => $device->id,
            'guest_count' => 2,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'terminal_session_id' => 1,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/orders/' . $order->order_id . '/printed', [
                'printed_at' => now()->toIso8601String(),
                'printer_id' => 'test-printer-01'
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Order marked as printed']);

        $this->assertDatabaseHas('device_orders', [
            'order_id' => $order->order_id,
            'is_printed' => 1,
            'printed_by' => 'test-printer-01',
        ]);

        Event::assertDispatched(PrintOrder::class);
    }

    public function test_mark_printed_bulk_partial_success()
    {
        Event::fake([PrintOrder::class]);

        $branch = \App\Models\Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);
        $device = Device::create(['name' => 'test-device', 'ip_address' => '192.168.1.101', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $order1 = DeviceOrder::create(['order_id' => 5001, 'device_id' => $device->id, 'guest_count' => 2, 'total' => 10, 'subtotal' => 10, 'is_printed' => 0, 'status' => 'confirmed', 'table_id' => 1, 'terminal_session_id' => 1, 'session_id' => 1]);
        $order2 = DeviceOrder::create(['order_id' => 5002, 'device_id' => $device->id, 'guest_count' => 2, 'total' => 20, 'subtotal' => 20, 'is_printed' => 1, 'status' => 'confirmed', 'table_id' => 1, 'terminal_session_id' => 1, 'session_id' => 1]); // already printed

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/orders/printed/bulk', [
                'order_ids' => [$order1->order_id, $order2->order_id, 99999],
                'printer_id' => 'bulk-printer-01',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['updated', 'already_printed', 'not_found']]);

        $this->assertDatabaseHas('device_orders', ['order_id' => $order1->order_id, 'is_printed' => 1, 'printed_by' => 'bulk-printer-01']);
        $this->assertDatabaseHas('device_orders', ['order_id' => $order2->order_id, 'is_printed' => 1]);

        Event::assertDispatched(PrintOrder::class);
    }
}
