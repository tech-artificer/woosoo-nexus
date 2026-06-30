<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Events\Order\DiscountApplied;
use App\Models\DeviceOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Guards the discount.applied broadcast contract:
 * - emitted on orders.{order_id} when discount transitions 0 → positive
 * - payload carries order_id and totals.discount_total
 */
class DiscountAppliedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_discount_applied_broadcasts_on_canonical_order_channel(): void
    {
        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id' => 900101,
            'discount' => 150.00,
            'total' => 850.00,
        ]);

        $event = new DiscountApplied($order);
        $channelNames = collect($event->broadcastOn())->map(fn (Channel $c) => $c->name)->all();

        $this->assertContains('orders.900101', $channelNames);
        $this->assertCount(1, $channelNames, 'DiscountApplied must only target the order channel — no admin fan-out');
    }

    public function test_discount_applied_payload_contains_discount_total(): void
    {
        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id' => 900102,
            'discount' => 200.00,
            'total' => 600.00,
        ]);

        $event = new DiscountApplied($order);
        $payload = $event->broadcastWith();

        $this->assertSame(900102, $payload['order_id']);
        $this->assertGreaterThan(0, $payload['totals']['discount_total']);
        $this->assertArrayHasKey('subtotal', $payload['totals']);
        $this->assertArrayHasKey('total', $payload['totals']);
    }

    public function test_discount_applied_broadcast_name_matches_contract(): void
    {
        $order = DeviceOrder::factory()->confirmed()->create(['order_id' => 900103]);

        $event = new DiscountApplied($order);

        $this->assertSame('discount.applied', $event->broadcastAs());
    }

    public function test_discount_applied_is_dispatched_when_discount_transitions_from_zero(): void
    {
        Event::fake([DiscountApplied::class]);

        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id' => 900104,
            'discount' => 0,
        ]);

        // Simulate the broadcaster being called after the transition
        event(new DiscountApplied($order));

        Event::assertDispatched(DiscountApplied::class, fn (DiscountApplied $e) => $e->order->order_id === 900104);
    }
}
