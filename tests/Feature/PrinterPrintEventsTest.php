<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PrinterPrintEventsTest extends TestCase
{
    use RefreshDatabase, MocksKryptonSession;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock active Krypton session for all tests
        $this->mockActiveKryptonSession();
    }

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

    public function test_heartbeat_updates_device_last_seen_at()
    {
        $branch = \App\Models\Branch::create(['name' => 'HB Branch', 'location' => 'Loc']);
        $device = Device::create(['name' => 'heartbeat-device', 'ip_address' => '127.0.0.2', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $before = now()->subMinutes(5);
        $device->update(['last_seen_at' => $before]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/heartbeat', [
                'printer_id' => 'PR2',
                'printer_name' => 'Test Printer',
            ])
            ->assertStatus(200);

        $device->refresh();
        $this->assertNotNull($device->last_seen_at);
        $this->assertTrue($device->last_seen_at->isAfter($before));
    }

    public function test_heartbeat_updates_device_app_version()
    {
        $branch = \App\Models\Branch::create(['name' => 'HB Branch', 'location' => 'Loc']);
        $device = Device::create(['name' => 'heartbeat-device', 'ip_address' => '127.0.0.3', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/heartbeat', [
                'printer_id' => 'PR3',
                'app_version' => '2.5.0',
            ])
            ->assertStatus(200);

        $device->refresh();
        $this->assertEquals('2.5.0', $device->app_version);
    }

    public function test_heartbeat_updates_device_status()
    {
        $branch = \App\Models\Branch::create(['name' => 'HB Branch', 'location' => 'Loc']);
        $device = Device::create(['name' => 'heartbeat-device', 'ip_address' => '127.0.0.4', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/heartbeat', [
                'printer_id' => 'PR4',
                'status' => 'printer_connected',
            ])
            ->assertStatus(200);

        $device->refresh();
        $this->assertEquals('printer_connected', $device->status);
    }

    public function test_heartbeat_rejects_device_id_mismatch()
    {
        $branch = \App\Models\Branch::create(['name' => 'HB Branch', 'location' => 'Loc']);
        $device = Device::create(['name' => 'heartbeat-device', 'ip_address' => '127.0.0.5', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/heartbeat', [
                'device_id' => 99999,  // Wrong device ID
                'printer_id' => 'PR5',
            ])
            ->assertStatus(403);
    }

    public function test_heartbeat_rejects_invalid_status()
    {
        $branch = \App\Models\Branch::create(['name' => 'HB Branch', 'location' => 'Loc']);
        $device = Device::create(['name' => 'heartbeat-device', 'ip_address' => '127.0.0.6', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/heartbeat', [
                'printer_id' => 'PR6',
                'status' => 'invalid_status',  // Not in enum
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_heartbeat_requires_authentication()
    {
        $this->postJson('/api/printer/heartbeat', [
            'printer_id' => 'PR7',
        ])
        ->assertStatus(401);
    }

    public function test_admin_can_query_online_devices_by_last_seen_at()
    {
        $branch = \App\Models\Branch::create(['name' => 'HB Branch', 'location' => 'Loc']);
        
        // Online device (seen recently)
        $onlineDevice = Device::create([
            'name' => 'online-device',
            'ip_address' => '127.0.0.7',
            'branch_id' => $branch->id,
            'last_seen_at' => now(),
        ]);
        
        // Offline device (not seen in 5 minutes)
        $offlineDevice = Device::create([
            'name' => 'offline-device',
            'ip_address' => '127.0.0.8',
            'branch_id' => $branch->id,
            'last_seen_at' => now()->subMinutes(5),
        ]);

        // Query for devices seen in last 2 minutes
        $onlineDevices = Device::where('last_seen_at', '>=', now()->subMinutes(2))->get();
        
        $this->assertTrue($onlineDevices->contains($onlineDevice));
        $this->assertFalse($onlineDevices->contains($offlineDevice));
    }

    public function test_polling_returns_only_unacked_events()
    {
        $branch = \App\Models\Branch::create(['name' => 'PollBranch', 'location' => 'P']);
        $device = Device::create(['name' => 'device-poll', 'ip_address' => '127.0.0.11', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 12121,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 2,
            'total' => 20,
            'subtotal' => 20,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $order->branch_id = $device->branch_id;
        $order->save();

        $acked = PrintEvent::factory()->create([
            'device_order_id' => $order->id,
            'is_acknowledged' => true,
        ]);
        $unacked = PrintEvent::factory()->create([
            'device_order_id' => $order->id,
            'is_acknowledged' => false,
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/printer/unprinted-events');

        $resp->assertStatus(200)->assertJsonPath('count', 1);
        $eventIds = collect($resp->json('events'))->pluck('id')->all();
        $this->assertContains($unacked->id, $eventIds);
        $this->assertNotContains($acked->id, $eventIds);
    }

    public function test_polling_respects_since_parameter()
    {
        $branch = \App\Models\Branch::create(['name' => 'SinceBranch', 'location' => 'S']);
        $device = Device::create(['name' => 'device-since', 'ip_address' => '127.0.0.12', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 13131,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $order->branch_id = $device->branch_id;
        $order->save();

        $base = Carbon::parse('2026-01-01 11:00:00', 'UTC');
        $oldEvent = PrintEvent::factory()->create([
            'device_order_id' => $order->id,
            'is_acknowledged' => false,
            'created_at' => $base->copy()->subHour(),
        ]);
        $newEvent = PrintEvent::factory()->create([
            'device_order_id' => $order->id,
            'is_acknowledged' => false,
            'created_at' => $base->copy()->addHour(),
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/printer/unprinted-events?since=2026-01-01%2011:00:00');

        $resp->assertStatus(200)->assertJsonPath('count', 1);
        $eventIds = collect($resp->json('events'))->pluck('id')->all();
        $this->assertNotContains($oldEvent->id, $eventIds);
        $this->assertContains($newEvent->id, $eventIds);
    }

    public function test_polling_alias_route_works()
    {
        $branch = \App\Models\Branch::create(['name' => 'AliasBranch', 'location' => 'A']);
        $device = Device::create(['name' => 'device-alias', 'ip_address' => '127.0.0.13', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 14141,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $order->branch_id = $device->branch_id;
        $order->save();

        $evt = PrintEvent::factory()->create([
            'device_order_id' => $order->id,
            'is_acknowledged' => false,
        ]);

        $primary = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/printer/unprinted-events');

        $alias = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/print-events/unprinted');

        $primary->assertStatus(200);
        $alias->assertStatus(200);
        $primaryIds = collect($primary->json('events'))->pluck('id')->all();
        $aliasIds = collect($alias->json('events'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing($primaryIds, $aliasIds);
        $this->assertContains($evt->id, $aliasIds);
    }

    public function test_polling_requires_device_token()
    {
        $this->getJson('/api/printer/unprinted-events')
            ->assertStatus(401);
    }

    public function test_device_uuid_immutable()
    {
        $branch = \App\Models\Branch::create(['name' => 'UuidBranch', 'location' => 'U']);
        $device = Device::create(['name' => 'device-uuid', 'ip_address' => '127.0.0.14', 'branch_id' => $branch->id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Device UUID is immutable');

        $device->device_uuid = '00000000-0000-0000-0000-000000000000';
        $device->save();
    }

    public function test_ack_forbidden_for_wrong_device_branch()
    {
        // Branch A with order/event
        $branchA = \App\Models\Branch::create(['name' => 'A', 'location' => 'A']);
        $deviceA = Device::create(['name' => 'device-a', 'ip_address' => '1.2.3.4', 'branch_id' => $branchA->id]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 11111,
            'device_id' => $deviceA->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
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

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 22222,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
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

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 33333,
            'device_id' => $deviceA->id,
            'branch_id' => $branchA->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
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

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 44444,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
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

    public function test_ack_returns_404_for_unknown_event()
    {
        $branch = \App\Models\Branch::create(['name' => 'X404', 'location' => 'X']);
        $device = Device::create(['name' => 'device-x404', 'ip_address' => '127.0.0.3', 'branch_id' => $branch->id]);

        $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/999999/ack', ['printer_id' => 'PR1'])
            ->assertStatus(404);
    }

    public function test_fail_returns_404_for_unknown_event()
    {
        $branch = \App\Models\Branch::create(['name' => 'Y404', 'location' => 'Y']);
        $device = Device::create(['name' => 'device-y404', 'ip_address' => '127.0.0.4', 'branch_id' => $branch->id]);

        $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/999998/failed', ['error' => 'Test'])
            ->assertStatus(404);
    }

    public function test_ack_without_printer_id_is_allowed()
    {
        $branch = \App\Models\Branch::create(['name' => 'NoPrinter', 'location' => 'NP']);
        $device = Device::create(['name' => 'device-np', 'ip_address' => '127.0.0.5', 'branch_id' => $branch->id]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 55555,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        $resp = $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/' . $evt->id . '/ack', []);

        $resp->assertStatus(200)->assertJsonPath('data.was_updated', true);
        $evt->refresh();
        $this->assertTrue($evt->is_acknowledged);
        $this->assertNull($evt->printer_id);
    }

    public function test_ack_invalid_printed_at_returns_422()
    {
        $branch = \App\Models\Branch::create(['name' => 'BadDate', 'location' => 'BD']);
        $device = Device::create(['name' => 'device-bd', 'ip_address' => '127.0.0.6', 'branch_id' => $branch->id]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 66666,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/' . $evt->id . '/ack', ['printer_id' => 'P1', 'printed_at' => 'not-a-date'])
            ->assertStatus(422);
    }

    public function test_fail_invalid_error_length_returns_422()
    {
        $branch = \App\Models\Branch::create(['name' => 'ErrLen', 'location' => 'EL']);
        $device = Device::create(['name' => 'device-el', 'ip_address' => '127.0.0.7', 'branch_id' => $branch->id]);

        $order = DeviceOrder::create([
            'order_id' => 77777,
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

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/' . $evt->id . '/failed', ['error' => str_repeat('x', 2000)])
            ->assertStatus(422);
    }

    public function test_get_unprinted_events_negative_limit_returns_422()
    {
        $branch = \App\Models\Branch::create(['name' => 'LimitBad', 'location' => 'LB']);
        $device = Device::create(['name' => 'device-lb', 'ip_address' => '127.0.0.8', 'branch_id' => $branch->id]);
        $this->actingAs($device, 'device')
            ->getJson('/api/printer/unprinted-events?limit=-1')
            ->assertStatus(422);
    }

    public function test_get_unprinted_events_invalid_since_returns_422()
    {
        $branch = \App\Models\Branch::create(['name' => 'SinceBad', 'location' => 'SB']);
        $device = Device::create(['name' => 'device-sb', 'ip_address' => '127.0.0.9', 'branch_id' => $branch->id]);
        $this->actingAs($device, 'device')
            ->getJson('/api/printer/unprinted-events?since=notadate')
            ->assertStatus(422);
    }

    public function test_fail_twice_increments_attempts()
    {
        $branch = \App\Models\Branch::create(['name' => 'TwiceFail', 'location' => 'TF']);
        $device = Device::create(['name' => 'device-tf', 'ip_address' => '127.0.0.9', 'branch_id' => $branch->id]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 88888,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $order->branch_id = $device->branch_id;
        $order->save();

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        $resp1 = $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/' . $evt->id . '/failed', ['error' => 'Network error']);

        $resp1->assertStatus(200)->assertJsonPath('data.attempts', 1);

        $resp2 = $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/' . $evt->id . '/failed', ['error' => 'Still failing']);

        $resp2->assertStatus(200)->assertJsonPath('data.attempts', 2);
    }

    public function test_fail_after_ack_is_noop()
    {
        $branch = \App\Models\Branch::create(['name' => 'FailAfterAck', 'location' => 'FA']);
        $device = Device::create(['name' => 'device-fa', 'ip_address' => '127.0.0.10', 'branch_id' => $branch->id]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 99999,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $order->branch_id = $device->branch_id;
        $order->save();

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        // Acknowledge first
        $ackResp = $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/' . $evt->id . '/ack', ['printer_id' => 'PA']);

        $ackResp->assertStatus(200)->assertJsonPath('data.was_updated', true);

        // Attempt to mark failed after ack — should be a noop
        $failResp = $this->actingAs($device, 'device')
            ->postJson('/api/printer/print-events/' . $evt->id . '/failed', ['error' => 'Paper jam']);

        $failResp->assertStatus(200)->assertJsonPath('data.was_updated', false);

        $evt->refresh();
        $this->assertEquals(1, $evt->attempts);
    }
}
