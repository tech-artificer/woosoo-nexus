<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Events\Order\OrderStatusUpdated;
use App\Events\PrintOrder;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_complete_single_order_and_observer_dispatches_events(): void
    {
        [$admin, $order] = $this->seedAdminAndOrder(5001, OrderStatus::CONFIRMED);

        Event::fake([OrderStatusUpdated::class, OrderCompleted::class]);

        $response = $this->actingAs($admin)->post('/orders/complete', [
            'order_id' => $order->order_id,
        ]);

        $response->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::COMPLETED->value, $order->status->value);

        Event::assertDispatched(OrderStatusUpdated::class);
        Event::assertDispatched(OrderCompleted::class);
    }

    public function test_admin_can_print_single_order(): void
    {
        [$admin, $order] = $this->seedAdminAndOrder(5002, OrderStatus::CONFIRMED);

        Event::fake([PrintOrder::class]);

        $response = $this->actingAs($admin)->post('/orders/print', [
            'order_id' => $order->order_id,
        ]);

        $response->assertRedirect();

        Event::assertDispatched(PrintOrder::class, function (PrintOrder $event) use ($order) {
            return $event->deviceOrder->id === $order->id;
        });
    }

    public function test_admin_bulk_complete_updates_all_matching_orders(): void
    {
        [$admin, $firstOrder] = $this->seedAdminAndOrder(5003, OrderStatus::CONFIRMED);
        [, $secondOrder] = $this->seedAdminAndOrder(5004, OrderStatus::SERVED);

        Event::fake([OrderStatusUpdated::class, OrderCompleted::class]);

        $response = $this->actingAs($admin)->post('/orders/bulk-complete', [
            'order_ids' => [$firstOrder->order_id, $secondOrder->order_id],
        ]);

        $response->assertRedirect();

        $firstOrder->refresh();
        $secondOrder->refresh();

        $this->assertSame(OrderStatus::COMPLETED->value, $firstOrder->status->value);
        $this->assertSame(OrderStatus::COMPLETED->value, $secondOrder->status->value);

        Event::assertDispatchedTimes(OrderStatusUpdated::class, 2);
        Event::assertDispatchedTimes(OrderCompleted::class, 2);
    }

    /**
     * @return array{0: User, 1: DeviceOrder}
     */
    private function seedAdminAndOrder(int $externalOrderId, OrderStatus $status): array
    {
        Branch::firstOrCreate(['name' => 'Main'], ['location' => 'HQ']);

        $device = Device::create([
            'name' => 'Admin Action Device ' . $externalOrderId,
            'ip_address' => '127.0.0.' . ((($externalOrderId % 200) + 1)),
            'is_active' => true,
            'table_id' => ($externalOrderId % 100) + 1,
        ]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'order_id' => $externalOrderId,
            'order_number' => 'ORD-' . $externalOrderId,
            'status' => $status->value,
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 0,
            'total' => 110,
            'guest_count' => 2,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        return [$admin, $order];
    }
}
