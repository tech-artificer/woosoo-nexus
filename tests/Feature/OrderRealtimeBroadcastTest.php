<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderVoided;
use App\Events\Order\OrderCancelled;
use App\Helpers\OrderBroadcastPayload;
use Illuminate\Broadcasting\Channel;

class OrderRealtimeBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;
    private int $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create(['name' => 'RT Branch', 'location' => 'RT']);
        $this->device = Device::create([
            'name'       => 'rt-device',
            'ip_address' => '127.9.0.1',
            'branch_id'  => $branch->id,
        ]);
        $this->sessionId = $this->createTestSession();
    }

    private function makeOrder(array $attributes = []): DeviceOrder
    {
        return DeviceOrder::create(array_merge([
            'device_id'          => $this->device->id,
            'branch_id'          => $this->device->branch_id,
            'table_id'           => 1,
            'terminal_session_id'=> 1,
            'session_id'         => $this->sessionId,
            'order_id'           => rand(100000, 999999),
            'order_number'       => 'ORD-RT-' . rand(1000, 9999),
            'status'             => OrderStatus::CONFIRMED->value,
            'subtotal'           => 100.00,
            'tax'                => 10.00,
            'discount'           => 0,
            'total'              => 110.00,
            'guest_count'        => 2,
            'is_printed'         => false,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // Observer: status-change dispatches
    // -------------------------------------------------------------------------

    public function test_observer_dispatches_order_status_updated_on_any_status_change(): void
    {
        Event::fake([OrderStatusUpdated::class]);

        $order = $this->makeOrder(['status' => OrderStatus::PENDING->value]);
        $order->update(['status' => OrderStatus::CONFIRMED->value]);

        Event::assertDispatched(OrderStatusUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id;
        });
    }

    public function test_observer_dispatches_order_completed_when_status_becomes_completed(): void
    {
        Event::fake([OrderStatusUpdated::class, OrderCompleted::class]);

        $order = $this->makeOrder(['status' => OrderStatus::CONFIRMED->value]);
        $order->update(['status' => OrderStatus::COMPLETED->value]);

        Event::assertDispatched(OrderStatusUpdated::class);
        Event::assertDispatched(OrderCompleted::class, function ($event) use ($order) {
            return $event->deviceOrder->id === $order->id;
        });
    }

    public function test_observer_dispatches_order_voided_when_status_becomes_voided(): void
    {
        Event::fake([OrderStatusUpdated::class, OrderVoided::class]);

        $order = $this->makeOrder(['status' => OrderStatus::CONFIRMED->value]);
        $order->update(['status' => OrderStatus::VOIDED->value]);

        Event::assertDispatched(OrderStatusUpdated::class);
        Event::assertDispatched(OrderVoided::class, function ($event) use ($order) {
            return $event->deviceOrder->id === $order->id;
        });
    }

    public function test_observer_dispatches_order_cancelled_when_status_becomes_cancelled(): void
    {
        Event::fake([OrderStatusUpdated::class, OrderCancelled::class]);

        $order = $this->makeOrder(['status' => OrderStatus::PENDING->value]);
        $order->update(['status' => OrderStatus::CANCELLED->value]);

        Event::assertDispatched(OrderStatusUpdated::class);
        Event::assertDispatched(OrderCancelled::class, function ($event) use ($order) {
            return $event->deviceOrder->id === $order->id;
        });
    }

    public function test_observer_does_not_dispatch_events_when_non_status_field_changes(): void
    {
        Event::fake([OrderStatusUpdated::class, OrderCompleted::class, OrderVoided::class, OrderCancelled::class]);

        $order = $this->makeOrder();
        $order->update(['guest_count' => 4]);

        Event::assertNotDispatched(OrderStatusUpdated::class);
        Event::assertNotDispatched(OrderCompleted::class);
        Event::assertNotDispatched(OrderVoided::class);
        Event::assertNotDispatched(OrderCancelled::class);
    }

    public function test_only_terminal_event_dispatched_for_each_terminal_status(): void
    {
        // completed → only OrderCompleted (not OrderVoided / OrderCancelled)
        Event::fake([OrderStatusUpdated::class, OrderCompleted::class, OrderVoided::class, OrderCancelled::class]);
        $order = $this->makeOrder(['status' => OrderStatus::CONFIRMED->value]);
        $order->update(['status' => OrderStatus::COMPLETED->value]);
        Event::assertDispatched(OrderStatusUpdated::class);
        Event::assertDispatched(OrderCompleted::class);
        Event::assertNotDispatched(OrderVoided::class);
        Event::assertNotDispatched(OrderCancelled::class);

        // voided → only OrderVoided (not OrderCompleted / OrderCancelled)
        Event::fake([OrderStatusUpdated::class, OrderCompleted::class, OrderVoided::class, OrderCancelled::class]);
        $order2 = $this->makeOrder(['status' => OrderStatus::CONFIRMED->value]);
        $order2->update(['status' => OrderStatus::VOIDED->value]);
        Event::assertDispatched(OrderStatusUpdated::class);
        Event::assertDispatched(OrderVoided::class);
        Event::assertNotDispatched(OrderCompleted::class);
        Event::assertNotDispatched(OrderCancelled::class);

        // cancelled → only OrderCancelled (not OrderCompleted / OrderVoided)
        Event::fake([OrderStatusUpdated::class, OrderCompleted::class, OrderVoided::class, OrderCancelled::class]);
        $order3 = $this->makeOrder(['status' => OrderStatus::PENDING->value]);
        $order3->update(['status' => OrderStatus::CANCELLED->value]);
        Event::assertDispatched(OrderStatusUpdated::class);
        Event::assertDispatched(OrderCancelled::class);
        Event::assertNotDispatched(OrderCompleted::class);
        Event::assertNotDispatched(OrderVoided::class);
    }

    // -------------------------------------------------------------------------
    // Broadcast channel and event-name contracts
    // -------------------------------------------------------------------------

    public function test_order_status_updated_broadcasts_on_correct_channels(): void
    {
        $order = $this->makeOrder();
        $event = new OrderStatusUpdated($order);
        $channels = $event->broadcastOn();

        $names = array_map(fn ($c) => $c->name, $channels);
        $this->assertContains("device.{$order->device_id}", $names);
        $this->assertContains('admin.orders', $names);
        $this->assertSame('order.updated', $event->broadcastAs());
    }

    public function test_order_completed_broadcasts_on_correct_channels(): void
    {
        $order = $this->makeOrder();
        $event = new OrderCompleted($order);
        $channels = $event->broadcastOn();

        $names = array_map(fn ($c) => $c->name, $channels);
        $this->assertContains("orders.{$order->order_id}", $names);
        $this->assertContains('admin.orders', $names);
        $this->assertSame('order.completed', $event->broadcastAs());
    }

    public function test_order_voided_broadcasts_on_correct_channels(): void
    {
        $order = $this->makeOrder();
        $event = new OrderVoided($order);
        $channels = $event->broadcastOn();

        $names = array_map(fn ($c) => $c->name, $channels);
        $this->assertContains("orders.{$order->order_id}", $names);
        $this->assertContains('admin.orders', $names);
        $this->assertSame('order.voided', $event->broadcastAs());
    }

    public function test_order_cancelled_broadcasts_on_correct_channels(): void
    {
        $order = $this->makeOrder();
        $event = new OrderCancelled($order);
        $channels = $event->broadcastOn();

        $names = array_map(fn ($c) => $c->name, $channels);
        $this->assertContains("orders.{$order->order_id}", $names);
        $this->assertContains('admin.orders', $names);
        $this->assertSame('order.cancelled', $event->broadcastAs());
    }

    // -------------------------------------------------------------------------
    // Payload shape consistency
    // -------------------------------------------------------------------------

    public function test_broadcast_payload_uses_subtotal_key(): void
    {
        $order = $this->makeOrder(['subtotal' => 250.00]);
        $payload = OrderBroadcastPayload::make($order);

        $this->assertArrayHasKey('subtotal', $payload, 'Payload must use "subtotal" not "sub_total"');
        $this->assertArrayNotHasKey('sub_total', $payload);
    }

    public function test_broadcast_payload_contains_required_keys(): void
    {
        $order = $this->makeOrder();
        $payload = OrderBroadcastPayload::make($order);

        $requiredKeys = ['id', 'status', 'subtotal', 'total', 'is_printed', 'device_id'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $payload, "Payload missing required key: {$key}");
        }
    }

    public function test_all_terminal_event_payloads_contain_order_key(): void
    {
        $order = $this->makeOrder();

        $events = [
            new OrderCompleted($order),
            new OrderVoided($order),
            new OrderCancelled($order),
            new OrderStatusUpdated($order),
        ];

        foreach ($events as $event) {
            $data = $event->broadcastWith();
            $this->assertArrayHasKey('order', $data, get_class($event) . '::broadcastWith() must wrap payload in "order" key');
            $this->assertIsArray($data['order']);
        }
    }
}
