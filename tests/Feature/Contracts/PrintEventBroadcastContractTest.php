<?php

namespace Tests\Feature\Contracts;

use App\Events\PrintOrder;
use App\Events\PrintRefill;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract Test: Backend → Relay Device (WebSocket Print Event Broadcast)
 * 
 * Verifies that backend print event broadcasts include all fields
 * required by the relay device's intake validation.
 * 
 * This test documents the contract and prevents breaking changes.
 */
class PrintEventBroadcastContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that PrintOrder event includes all required flat fields
     * for relay device consumption.
     */
    public function test_print_order_event_includes_required_fields_for_relay_device(): void
    {
        // Arrange: Create test data
        $device = Device::factory()->create(['id' => 5]);
        $deviceOrder = DeviceOrder::factory()->create([
            'device_id' => $device->id,
            'order_id' => 123,
            'order_number' => 'ORD-001',
            'session_id' => 456,
        ]);
        
        $printEvent = PrintEvent::factory()->create([
            'device_order_id' => $deviceOrder->id,
            'event_type' => 'INITIAL',
        ]);
        
        $deviceOrder->refresh();
        // PHASE 2: Removed setRelation('printEvent') hack - test now proves real production behavior

        // Act: Create broadcast event
        $event = new PrintOrder($deviceOrder);
        $payload = $event->broadcastWith();

        // Assert: Required top-level fields (flat, not nested)
        $this->assertArrayHasKey('print_event_id', $payload, 'Missing print_event_id');
        $this->assertArrayHasKey('device_id', $payload, 'Missing device_id');
        $this->assertArrayHasKey('order_id', $payload, 'Missing order_id');
        $this->assertArrayHasKey('session_id', $payload, 'Missing session_id');
        $this->assertArrayHasKey('print_type', $payload, 'Missing print_type');
        $this->assertArrayHasKey('refill_number', $payload, 'Missing refill_number');

        // Assert: Field types and values
        $this->assertIsInt($payload['print_event_id']);
        $this->assertGreaterThan(0, $payload['print_event_id'], 'print_event_id must be > 0');
        
        $this->assertIsInt($payload['device_id']);
        $this->assertGreaterThan(0, $payload['device_id'], 'device_id must be > 0');
        $this->assertEquals($device->id, $payload['device_id']);
        
        $this->assertIsInt($payload['order_id']);
        $this->assertGreaterThan(0, $payload['order_id'], 'order_id must be > 0');
        $this->assertEquals(123, $payload['order_id']);
        
        $this->assertEquals('INITIAL', $payload['print_type']);
        $this->assertNull($payload['refill_number']);

        // Assert: Nested order data also present (for printing)
        $this->assertArrayHasKey('order', $payload);
        $this->assertArrayHasKey('items', $payload);
        $this->assertIsArray($payload['items']);
    }

    /**
     * Test that PrintRefill event includes all required flat fields
     * for relay device consumption.
     */
    public function test_print_refill_event_includes_required_fields_for_relay_device(): void
    {
        // Arrange: Create test data
        $device = Device::factory()->create(['id' => 7]);
        $deviceOrder = DeviceOrder::factory()->create([
            'device_id' => $device->id,
            'order_id' => 789,
            'order_number' => 'ORD-002',
            'session_id' => 999,
            'refill_number' => 2,
        ]);
        
        $printEvent = PrintEvent::factory()->create([
            'device_order_id' => $deviceOrder->id,
            'event_type' => 'REFILL',
        ]);
        
        $deviceOrder->refresh();
        // PHASE 2: Removed setRelation('printEvent') hack - test now proves real production behavior

        $items = [
            ['name' => 'Beef Brisket', 'quantity' => 2],
            ['name' => 'Kimchi', 'quantity' => 1],
        ];

        // Act: Create broadcast event
        $event = new PrintRefill($deviceOrder, $items);
        $payload = $event->broadcastWith();

        // Assert: Required top-level fields (flat, not nested)
        $this->assertArrayHasKey('print_event_id', $payload, 'Missing print_event_id');
        $this->assertArrayHasKey('device_id', $payload, 'Missing device_id');
        $this->assertArrayHasKey('order_id', $payload, 'Missing order_id');
        $this->assertArrayHasKey('session_id', $payload, 'Missing session_id');
        $this->assertArrayHasKey('print_type', $payload, 'Missing print_type');
        $this->assertArrayHasKey('refill_number', $payload, 'Missing refill_number');

        // Assert: Field types and values
        $this->assertIsInt($payload['print_event_id']);
        $this->assertGreaterThan(0, $payload['print_event_id']);
        
        $this->assertIsInt($payload['device_id']);
        $this->assertEquals($device->id, $payload['device_id']);
        
        $this->assertIsInt($payload['order_id']);
        $this->assertEquals(789, $payload['order_id']);
        
        $this->assertEquals('REFILL', $payload['print_type']);
        $this->assertEquals(2, $payload['refill_number']);

        // Assert: Items array
        $this->assertArrayHasKey('items', $payload);
        $this->assertIsArray($payload['items']);
        $this->assertCount(2, $payload['items']);
        
        foreach ($payload['items'] as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('quantity', $item);
        }
    }

    /**
     * Test that broadcast channel matches relay device expectations.
     */
    public function test_print_events_broadcast_on_correct_channel(): void
    {
        // Arrange
        $device = Device::factory()->create();
        $deviceOrder = DeviceOrder::factory()->create(['device_id' => $device->id]);
        $printEvent = PrintEvent::factory()->create(['device_order_id' => $deviceOrder->id]);
        
        $deviceOrder->refresh();
        // PHASE 2: Removed setRelation('printEvent') hack - test now proves real production behavior

        // Act: Get broadcast channels
        $printOrderEvent = new PrintOrder($deviceOrder);
        $printRefillEvent = new PrintRefill($deviceOrder, []);

        $printOrderChannels = $printOrderEvent->broadcastOn();
        $printRefillChannels = $printRefillEvent->broadcastOn();

        // Assert: Both use 'admin.print' channel
        $this->assertCount(1, $printOrderChannels);
        $this->assertEquals('admin.print', $printOrderChannels[0]->name);
        
        $this->assertCount(1, $printRefillChannels);
        $this->assertEquals('admin.print', $printRefillChannels[0]->name);
    }

    /**
     * Test that event name matches relay device expectations.
     */
    public function test_print_events_broadcast_with_correct_event_name(): void
    {
        // Arrange
        $device = Device::factory()->create();
        $deviceOrder = DeviceOrder::factory()->create(['device_id' => $device->id]);
        $printEvent = PrintEvent::factory()->create(['device_order_id' => $deviceOrder->id]);
        
        $deviceOrder->refresh();
        // PHASE 2: Removed setRelation('printEvent') hack - test now proves real production behavior

        // Act: Get broadcast event names
        $printOrderEvent = new PrintOrder($deviceOrder);
        $printRefillEvent = new PrintRefill($deviceOrder, []);

        // Assert: Event names
        $this->assertEquals('order.printed', $printOrderEvent->broadcastAs());
        $this->assertEquals('order.printed', $printRefillEvent->broadcastAs());
    }

    /**
     * Test contract violation: missing device_id should be caught.
     */
    public function test_contract_violation_missing_device_id(): void
    {
        // Arrange: Create order with null device_id (edge case)
        $deviceOrder = DeviceOrder::factory()->create(['device_id' => null]);
        $printEvent = PrintEvent::factory()->create(['device_order_id' => $deviceOrder->id]);
        
        $deviceOrder->refresh();
        // PHASE 2: Removed setRelation('printEvent') hack - test now proves real production behavior

        // Act: Create broadcast event
        $event = new PrintOrder($deviceOrder);
        $payload = $event->broadcastWith();

        // Assert: device_id is present but null
        $this->assertArrayHasKey('device_id', $payload);
        $this->assertNull($payload['device_id']);
        
        // Note: Relay device should reject this payload (device_id validation)
        // This test documents the edge case
    }

    /**
     * Test contract violation: missing print_event_id should be caught.
     */
    public function test_contract_violation_missing_print_event_id(): void
    {
        // Arrange: Create order without PrintEvent relation
        $device = Device::factory()->create();
        $deviceOrder = DeviceOrder::factory()->create(['device_id' => $device->id]);
        // No printEvent attached

        // Act: Create broadcast event
        $event = new PrintOrder($deviceOrder);
        $payload = $event->broadcastWith();

        // Assert: print_event_id is present but null
        $this->assertArrayHasKey('print_event_id', $payload);
        $this->assertNull($payload['print_event_id']);
        
        // Note: Relay device should reject this payload (print_event_id <= 0)
        // This test documents the edge case
    }

    /**
     * Test that payload includes all fields needed for receipt printing.
     */
    public function test_payload_includes_receipt_printing_data(): void
    {
        // Arrange: Create full order with items
        $device = Device::factory()->create();
        $deviceOrder = DeviceOrder::factory()
            ->has(\App\Models\DeviceOrderItem::factory()->count(3), 'items')
            ->create(['device_id' => $device->id]);
        
        $printEvent = PrintEvent::factory()->create(['device_order_id' => $deviceOrder->id]);
        
        $deviceOrder->refresh();
        $deviceOrder->setRelation('printEvent', $printEvent);

        // Act: Create broadcast event
        $event = new PrintOrder($deviceOrder);
        $payload = $event->broadcastWith();

        // Assert: Receipt printing data
        $this->assertArrayHasKey('order', $payload);
        $this->assertArrayHasKey('items', $payload);
        $this->assertArrayHasKey('created_at', $payload);
        
        $this->assertIsArray($payload['items']);
        $this->assertCount(3, $payload['items']);
        
        foreach ($payload['items'] as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('quantity', $item);
            $this->assertArrayHasKey('price', $item);
        }
    }
}
