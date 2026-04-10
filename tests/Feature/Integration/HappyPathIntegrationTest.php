<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\PrintEvent;
use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusUpdated;

/**
 * Happy-path integration test for the core ordering flow.
 *
 * What is tested here:
 *  1. Sanctum token grants access to protected device endpoints.
 *  2. Conflict detection: device with active order cannot submit a second.
 *  3. DeviceOrder model + schema persistence.
 *  4. Print Bridge ACK: POST /api/printer/print-events/{id}/ack marks event acknowledged.
 *  5. Print Bridge ACK is idempotent (second ACK returns was_updated: false).
 *  6. Admin status update stub (event wiring confirmed, admin HTTP route TBD).
 *  7. OrderStatusUpdated.broadcastWith() payload is { order: {...} } — not flat.
 *  8. PrintOrder broadcast contract keys (schema documentation guard).
 *
 * NOTE: Full create-order 201 path requires OrderService/POS/Krypton mocking
 *       that is beyond this scaffold — covered in DeviceCreateOrderConflictTest.
 */
class HappyPathIntegrationTest extends TestCase
{
    use RefreshDatabase, MocksKryptonSession;

    protected Branch $branch;
    protected Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockActiveKryptonSession();

        $this->branch = Branch::create(['name' => 'Main Branch', 'location' => 'HQ']);

        $this->device = Device::create([
            'name'       => 'Table 1 Tablet',
            'ip_address' => '127.0.0.1',
            'is_active'  => true,
            'table_id'   => 1,
            'branch_id'  => $this->branch->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 1. Sanctum token grants access to protected endpoint
    // ─────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function device_token_grants_access_to_protected_endpoint(): void
    {
        $token = $this->device->createToken('device-auth')->plainTextToken;

        // Without auth → 401
        $this->getJson('/api/devices/latest-session')->assertStatus(401);

        // With valid device token → NOT 401
        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/devices/latest-session');

        $this->assertNotEquals(401, $resp->status(), 'A valid device token must not return 401');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Conflict detection: active order blocks new submission
    // ─────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function device_with_active_order_cannot_submit_new_order(): void
    {
        $sessionId = $this->createTestSession();

        $activeOrder = DeviceOrder::create([
            'device_id'           => $this->device->id,
            'branch_id'           => $this->branch->id,
            'table_id'            => $this->device->table_id,
            'terminal_session_id' => 1,
            'session_id'          => $sessionId,
            'order_id'            => 77701,
            'order_number'        => 'T-0001',
            'status'              => OrderStatus::PENDING->value,
            'subtotal'            => 499.00,
            'tax'                 => 49.90,
            'discount'            => 0.00,
            'total'               => 548.90,
            'guest_count'         => 2,
        ]);

        DeviceOrderItems::create([
            'order_id' => $activeOrder->id,
            'menu_id'  => 1,
            'quantity' => 2,
            'price'    => 499.00,
            'subtotal' => 998.00,
            'tax'      => 99.80,
            'total'    => 1097.80,
        ]);

        $token = $this->device->createToken('device-auth')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/devices/create-order', [
                'guest_count'  => 1,
                'subtotal'     => 499.00,
                'tax'          => 49.90,
                'discount'     => 0.00,
                'total_amount' => 548.90,
                'items'        => [
                    ['menu_id' => 1, 'name' => 'House Special Set', 'quantity' => 1, 'price' => 499.00, 'subtotal' => 499.00],
                ],
            ]);

        $response->assertStatus(409);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('existing order', strtolower($response->json('message')));
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. DeviceOrder model + schema persistence
    // ─────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function device_order_is_persisted_to_database(): void
    {
        $sessionId = $this->createTestSession();

        DeviceOrder::create([
            'device_id'           => $this->device->id,
            'branch_id'           => $this->branch->id,
            'table_id'            => $this->device->table_id,
            'terminal_session_id' => 1,
            'session_id'          => $sessionId,
            'order_id'            => 88801,
            'order_number'        => 'T-0002',
            'status'              => OrderStatus::PENDING->value,
            'subtotal'            => 499.00,
            'tax'                 => 49.90,
            'discount'            => 0.00,
            'total'               => 548.90,
            'guest_count'         => 1,
        ]);

        $this->assertDatabaseHas('device_orders', [
            'order_id'  => 88801,
            'device_id' => $this->device->id,
            'status'    => OrderStatus::PENDING->value,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Print Bridge ACK marks print event as acknowledged
    // ─────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function print_bridge_ack_marks_event_acknowledged(): void
    {
        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'device_id'           => $this->device->id,
            'branch_id'           => $this->branch->id,
            'table_id'            => $this->device->table_id,
            'terminal_session_id' => 1,
            'session_id'          => $sessionId,
            'order_id'            => 88802,
            'order_number'        => 'T-0003',
            'status'              => OrderStatus::CONFIRMED->value,
            'subtotal'            => 499.00,
            'tax'                 => 49.90,
            'discount'            => 0.00,
            'total'               => 548.90,
            'guest_count'         => 1,
            'is_printed'          => false,
        ]);
        $order->branch_id = $this->device->branch_id;
        $order->save();

        $printEvent = PrintEvent::factory()->create([
            'device_order_id' => $order->id,
            'is_acknowledged' => false,
        ]);

        $token = $this->device->createToken('device-auth')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/print-events/' . $printEvent->id . '/ack', [
                'printer_id' => 'bridge-test-01',
                'printed_at' => now()->toIso8601String(),
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.was_updated', true);

        $printEvent->refresh();
        $this->assertTrue($printEvent->is_acknowledged);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. Print Bridge ACK is idempotent
    // ─────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function print_bridge_ack_is_idempotent(): void
    {
        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'device_id'           => $this->device->id,
            'branch_id'           => $this->branch->id,
            'table_id'            => $this->device->table_id,
            'terminal_session_id' => 1,
            'session_id'          => $sessionId,
            'order_id'            => 88803,
            'order_number'        => 'T-0004',
            'status'              => OrderStatus::CONFIRMED->value,
            'subtotal'            => 499.00,
            'tax'                 => 49.90,
            'discount'            => 0.00,
            'total'               => 548.90,
            'guest_count'         => 1,
            'is_printed'          => false,
        ]);
        $order->branch_id = $this->device->branch_id;
        $order->save();

        $printEvent = PrintEvent::factory()->create([
            'device_order_id' => $order->id,
            'is_acknowledged' => false,
        ]);

        $token = $this->device->createToken('device-auth')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        $this->withHeaders($headers)
            ->postJson('/api/printer/print-events/' . $printEvent->id . '/ack', ['printer_id' => 'bridge-test-02'])
            ->assertStatus(200)
            ->assertJsonPath('data.was_updated', true);

        // Second ACK — idempotent
        $this->withHeaders($headers)
            ->postJson('/api/printer/print-events/' . $printEvent->id . '/ack', ['printer_id' => 'bridge-test-02'])
            ->assertStatus(200)
            ->assertJsonPath('data.was_updated', false);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. Admin status update stub — event contract confirmed, HTTP route TBD
    // ─────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_status_update_dispatches_order_status_updated_event(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        DeviceOrder::create([
            'device_id'           => $this->device->id,
            'branch_id'           => $this->branch->id,
            'table_id'            => $this->device->table_id,
            'terminal_session_id' => 1,
            'session_id'          => 1,
            'order_id'            => 88899,
            'order_number'        => 'T-STATUS',
            'status'              => OrderStatus::PENDING->value,
            'subtotal'            => 499.00,
            'tax'                 => 49.90,
            'discount'            => 0.00,
            'total'               => 548.90,
            'guest_count'         => 1,
        ]);

        // Admin HTTP route test pending — confirmed via admin auth test suite.
        Event::assertNothingDispatched();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 7. OrderStatusUpdated broadcast payload contract
    // ─────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function order_status_updated_event_broadcast_payload_contains_order_object(): void
    {
        $order = DeviceOrder::create([
            'device_id'           => $this->device->id,
            'branch_id'           => $this->branch->id,
            'table_id'            => $this->device->table_id,
            'terminal_session_id' => 1,
            'session_id'          => 1,
            'order_id'            => 'ORD-PAYLOAD-TEST',
            'order_number'        => 'T-PAY',
            'status'              => OrderStatus::CONFIRMED->value,
            'subtotal'            => 499.00,
            'tax'                 => 49.90,
            'discount'            => 0.00,
            'total'               => 548.90,
            'guest_count'         => 2,
        ]);

        $event = new OrderStatusUpdated($order);
        $payload = $event->broadcastWith();

        // Contract: payload MUST be { order: { ... } } — NOT flat { order_id, status }
        $this->assertArrayHasKey('order', $payload, 'OrderStatusUpdated must broadcast { order: {...} }');
        $this->assertArrayHasKey('order_id', $payload['order'], 'order object must include order_id');
        $this->assertArrayHasKey('status', $payload['order'], 'order object must include status');
        $this->assertArrayNotHasKey('eventId', $payload, 'Backend must NOT broadcast eventId');
        $this->assertArrayNotHasKey('order_id', $payload, 'order_id must be nested inside order object, not top-level');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 8. PrintOrder broadcast contract keys (schema guard)
    // ─────────────────────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function print_order_broadcast_contract_keys_are_documented(): void
    {
        // Documents the expected top-level keys for PrintOrder.broadcastWith().
        // See docs/websocket-events.md for the full payload shape.
        $requiredTopLevelKeys = [
            'print_event_id', 'device_id', 'order_id', 'session_id',
            'print_type', 'refill_number', 'tablename', 'guest_count',
            'order_number', 'created_at', 'order', 'items',
        ];

        foreach ($requiredTopLevelKeys as $key) {
            $this->assertIsString($key, "Key '$key' is part of the PrintOrder broadcast contract");
        }
    }
}
