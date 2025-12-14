<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

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

    public function test_ack_success_and_idempotent()
    {
        $branch = \App\Models\Branch::create(['name' => 'BranchX', 'location' => 'X']);
        $device = Device::create(['name' => 'device-x', 'ip_address' => '1.2.3.5', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $order = DeviceOrder::create([
            'order_id' => 22222,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        // Ensure the DeviceOrder branch matches the created device (DeviceOrder
        // model sets branch_id during creating(), so normalise to avoid mismatch).
        $order->branch_id = $device->branch_id;
        $order->save();

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        // First ack should perform the update
        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/print-events/' . $evt->id . '/ack', ['printer_id' => 'PR1', 'printed_at' => '2025-12-15T12:00:00+08:00']);

        $resp->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.was_updated', true);

        $evt->refresh();
        $this->assertTrue($evt->is_acknowledged);

        // The DB stores the timestamp without timezone information. Compare
        // the raw DB stored datetime string against the expected UTC time
        // so that the assertion is timezone-agnostic.
        $raw = \Illuminate\Support\Facades\DB::table('print_events')->where('id', $evt->id)->value('acknowledged_at');
        $expectedUtc = Carbon::parse('2025-12-15T12:00:00+08:00')->utc()->format('Y-m-d H:i:s');
        $this->assertEquals($expectedUtc, $raw);

        // Second ack is idempotent (was_updated => false)
        $resp2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/print-events/' . $evt->id . '/ack', ['printer_id' => 'PR1']);

        $resp2->assertStatus(200)->assertJsonPath('data.was_updated', false);
    }

    public function test_fail_increments_attempts_and_forbidden_for_wrong_device()
    {
        $branchA = \App\Models\Branch::create(['name' => 'A2', 'location' => 'A2']);
        $deviceA = Device::create(['name' => 'device-a2', 'ip_address' => '1.2.3.6', 'branch_id' => $branchA->id]);

        $order = DeviceOrder::create([
            'order_id' => 33333,
            'device_id' => $deviceA->id,
            'branch_id' => $branchA->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        // Normalise branch to avoid DeviceOrder::creating() overriding
        $order->branch_id = $deviceA->branch_id;
        $order->save();

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);
        $order->refresh();
        $evt->refresh();
        $this->assertEquals($order->id, $evt->device_order_id);
        $this->assertEquals($deviceA->branch_id, $order->branch_id);

        // Other branch device should be forbidden
        $branchB = \App\Models\Branch::create(['name' => 'B2', 'location' => 'B2']);
        $deviceB = Device::create(['name' => 'device-b2', 'ip_address' => '5.6.7.9', 'branch_id' => $branchB->id]);
        $tokenB = $deviceB->createToken('device-auth')->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson('/api/printer/print-events/' . $evt->id . '/failed', ['error' => 'Paper jam']);

        $resp->assertStatus(403);

        // Authorized device marks fail
        // Authenticate as the device directly for the authorized call.
        $resp2 = $this->actingAs($deviceA, 'device')
            ->postJson('/api/printer/print-events/' . $evt->id . '/failed', ['error' => 'Paper jam']);

        $resp2->assertStatus(200)->assertJson(['success' => true]);
        $evt->refresh();
        $this->assertEquals(1, $evt->attempts);
    }

    public function test_get_unprinted_events_limit_cap()
    {
        $branch = \App\Models\Branch::create(['name' => 'LimitBranch', 'location' => 'L']);
        $device = Device::create(['name' => 'device-limit', 'ip_address' => '127.0.0.2', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $order = DeviceOrder::create([
            'order_id' => 44444,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        // Normalise branch to avoid DeviceOrder::creating() overriding
        $order->branch_id = $device->branch_id;
        $order->save();

        // Create more than the max cap (e.g., 205 events)
        for ($i = 0; $i < 205; $i++) {
            PrintEvent::factory()->create(['device_order_id' => $order->id]);
        }

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/printer/unprinted-events?limit=1000');

        $resp->assertStatus(200);
        $this->assertLessThanOrEqual(200, $resp->json('count'));
    }
}
