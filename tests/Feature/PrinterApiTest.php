<?php

namespace Tests\Feature;

use App\Events\Order\OrderPrinted;
use App\Events\PrintOrder;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;

class PrinterApiTest extends TestCase
{
    use MocksKryptonSession, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock active Krypton session for all tests
        $this->mockActiveKryptonSession();
    }

    public function test_mark_printed_sets_flags_and_dispatches_events()
    {
        Event::fake([PrintOrder::class, OrderPrinted::class]);

        // Create branch and device directly
        $branch = Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);
        $device = Device::create(['name' => 'test-device', 'ip_address' => '192.168.1.100', 'branch_id' => $branch->id]);

        // token
        $token = $device->createToken('device-auth')->plainTextToken;

        $sessionId = $this->createTestSession();

        // Create a device order
        $order = DeviceOrder::create([
            'order_id' => 999999,
            'device_id' => $device->id,
            'guest_count' => 2,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/orders/'.$order->order_id.'/printed', [
                'printed_at' => now()->toIso8601String(),
                'printer_id' => 'test-printer-01',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Order marked as printed']);

        $this->assertDatabaseHas('device_orders', [
            'order_id' => $order->order_id,
            'is_printed' => 1,
            'printed_by' => 'test-printer-01',
        ]);

        // Ack path emits OrderPrinted (post-print state notification) — never PrintOrder (print command).
        // Dispatching PrintOrder from an ack was the root cause of duplicate kitchen tickets (nex-case-011).
        Event::assertDispatched(OrderPrinted::class);
        Event::assertNotDispatched(PrintOrder::class);
    }

    public function test_mark_printed_returns_early_when_already_printed()
    {
        Event::fake([PrintOrder::class, OrderPrinted::class]);

        $branch = Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);
        $device = Device::create(['name' => 'test-device', 'ip_address' => '192.168.1.102', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 777777,
            'device_id' => $device->id,
            'guest_count' => 2,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => true,
            'printed_at' => now()->subMinutes(5),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/orders/'.$order->order_id.'/printed', [
                'printed_at' => now()->toIso8601String(),
                'printer_id' => 'retry-printer-01',
            ]);

        // Idempotency guard: returns success with original printed_at, no side effects
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'message' => 'Order was already printed']);

        // No events fired on idempotent re-ack — bridge retry must not trigger another print
        Event::assertNotDispatched(PrintOrder::class);
        Event::assertNotDispatched(OrderPrinted::class);
    }

    public function test_print_endpoint_returns_success_envelope()
    {
        $branch = Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);
        $device = Device::create(['name' => 'test-device', 'ip_address' => '192.168.1.200', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $tableId = DB::connection('pos')->table('tables')->insertGetId([
            'name' => 'Print Test Table',
            'is_available' => true,
            'is_locked' => false,
        ]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 222222,
            'device_id' => $device->id,
            'guest_count' => 2,
            'total' => 10,
            'subtotal' => 10,
            'table_id' => $tableId,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/order/'.$order->order_id.'/print');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('tablename', 'Print Test Table')
            ->assertJsonStructure(['success', 'order', 'tablename', 'items']);
    }

    public function test_mark_printed_bulk_partial_success()
    {
        Event::fake([PrintOrder::class, OrderPrinted::class]);

        $branch = Branch::create(['name' => 'Test Branch', 'location' => 'Test Location']);
        $device = Device::create(['name' => 'test-device', 'ip_address' => '192.168.1.101', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $sessionId = $this->createTestSession();

        $order1 = DeviceOrder::create(['order_id' => 5001, 'device_id' => $device->id, 'guest_count' => 2, 'total' => 10, 'subtotal' => 10, 'is_printed' => 0, 'status' => 'confirmed', 'table_id' => 1, 'terminal_session_id' => 1, 'session_id' => $sessionId]);
        $order2 = DeviceOrder::create(['order_id' => 5002, 'device_id' => $device->id, 'guest_count' => 2, 'total' => 20, 'subtotal' => 20, 'is_printed' => 1, 'status' => 'confirmed', 'table_id' => 1, 'terminal_session_id' => 1, 'session_id' => $sessionId]); // already printed

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/orders/printed/bulk', [
                'order_ids' => [$order1->order_id, $order2->order_id, 99999],
                'printer_id' => 'bulk-printer-01',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['updated', 'already_printed', 'not_found']]);

        $this->assertDatabaseHas('device_orders', ['order_id' => $order1->order_id, 'is_printed' => 1, 'printed_by' => 'bulk-printer-01']);
        $this->assertDatabaseHas('device_orders', ['order_id' => $order2->order_id, 'is_printed' => 1]);

        // Bulk ack: OrderPrinted fires for newly-printed orders; PrintOrder must never fire from an ack path.
        Event::assertDispatched(OrderPrinted::class);
        Event::assertNotDispatched(PrintOrder::class);
    }
}
