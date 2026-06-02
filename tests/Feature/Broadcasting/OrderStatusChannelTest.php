<?php

declare(strict_types=1);

namespace Tests\Feature\Broadcasting;

use App\Events\Order\OrderDetailsUpdated;
use App\Events\Order\OrderStatusUpdated;
use App\Models\DeviceOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NEX-CASE-013: the channel correctness bug regression guard.
 *
 * Tablet listeners are keyed on `orders.{order_id}` (per
 * `contracts/websocket-events.contract.md`). Prior to NEX-CASE-013,
 * `OrderStatusUpdated::broadcastOn` returned only `device.{device_id}` +
 * `admin.orders`, so tablets never received status transitions — a silent
 * terminal-event drop in the SessionReset family. Lock the fix in place.
 */
class OrderStatusChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_status_updated_broadcasts_on_canonical_order_channel(): void
    {
        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id' => 800101,
            'device_id' => 42,
        ]);

        $event = new OrderStatusUpdated($order);
        $channelNames = $this->channelNames($event->broadcastOn());

        $this->assertContains(
            'orders.800101',
            $channelNames,
            'OrderStatusUpdated must broadcast on the canonical orders.{order_id} channel'
        );
    }

    public function test_order_details_updated_does_not_broadcast_on_legacy_device_channel(): void
    {
        $order = DeviceOrder::factory()->confirmed()->create([
            'order_id' => 800102,
            'device_id' => 43,
        ]);

        $event = new OrderDetailsUpdated($order);
        $channelNames = $this->channelNames($event->broadcastOn());

        $this->assertSame(
            ['orders.800102', 'admin.orders'],
            $channelNames,
            'OrderDetailsUpdated channels must be exactly the canonical pair — no device fan-out'
        );
    }

    /**
     * @param  array<int, Channel>  $channels
     * @return array<int, string>
     */
    private function channelNames(array $channels): array
    {
        return array_map(static fn (Channel $channel): string => $channel->name, $channels);
    }
}
