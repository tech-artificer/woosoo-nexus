<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use Illuminate\Support\Facades\Cache;

class PrinterPrintEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_sets_cache()
    {
        // Create branch and device
        $branch = \App\Models\Branch::create(['name' => 'HB Branch', 'location' => 'Loc']);
        $device = Device::create(['name' => 'heartbeat-device', 'ip_address' => '127.0.0.1', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/heartbeat', [
                'printer_id' => 'PR1',
                'printer_name' => 'Test Printer',
                'session_id' => 1,
                'last_printed_order_id' => null,
            ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);
        $this->assertTrue(Cache::has('printer:heartbeat:PR1'));
    }

    public function test_ack_forbidden_for_wrong_device_branch()
    {
        // Branch A with order/event
        $branchA = \App\Models\Branch::create(['name' => 'A', 'location' => 'A']);
        $deviceA = Device::create(['name' => 'device-a', 'ip_address' => '1.2.3.4', 'branch_id' => $branchA->id]);

        $order = DeviceOrder::create([
            'order_id' => 11111,
            'device_id' => $deviceA->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        // Branch B device (unauthorized)
        $branchB = \App\Models\Branch::create(['name' => 'B', 'location' => 'B']);
        $deviceB = Device::create(['name' => 'device-b', 'ip_address' => '5.6.7.8', 'branch_id' => $branchB->id]);
        $tokenB = $deviceB->createToken('device-auth')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson('/api/printer/print-events/' . $evt->id . '/ack', ['printer_id' => 'PRX']);

        $resp->assertStatus(403);
    }
}
