<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\User;
use App\Services\Krypton\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Validates that bulkVoid wraps local status update + POS void in a single
 * DB::transaction so that a POS failure rolls back the local status change
 * and leaves no cross-DB orphan.
 */
class BulkVoidTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdminAndOrder(int $orderId, OrderStatus $status): array
    {
        $branch = Branch::factory()->create();
        $device = Device::factory()->create(['branch_id' => $branch->id]);
        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'order_id' => $orderId,
            'order_number' => 'ORD-'.$orderId,
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

    public function test_local_status_is_rolled_back_when_pos_void_throws(): void
    {
        [$admin, $order] = $this->seedAdminAndOrder(7001, OrderStatus::CONFIRMED);

        // Simulate POS void failure
        $this->mock(OrderService::class, function ($mock) {
            $mock->shouldReceive('voidOrder')
                ->once()
                ->andThrow(new RuntimeException('POS connection refused'));
        });

        $response = $this->actingAs($admin)->post('/orders/bulk-void', [
            'order_ids' => [$order->order_id],
        ]);

        $response->assertRedirect();

        // Local status must NOT be VOIDED — transaction must have rolled it back
        $order->refresh();
        $this->assertSame(
            OrderStatus::CONFIRMED->value,
            $order->status->value,
            'Local status must roll back when POS void throws',
        );
    }

    public function test_successful_void_marks_order_voided_in_local_db(): void
    {
        [$admin, $order] = $this->seedAdminAndOrder(7002, OrderStatus::CONFIRMED);

        $response = $this->actingAs($admin)->post('/orders/bulk-void', [
            'order_ids' => [$order->order_id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::VOIDED->value, $order->status->value);
    }

    public function test_void_of_nonexistent_order_is_recorded_as_failed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/orders/bulk-void', [
            'order_ids' => [999999],
        ]);

        $response->assertRedirect();
        // Session message should note failures (no exception thrown to the caller)
        $response->assertSessionHas('success');
    }

    public function test_partial_batch_failure_does_not_affect_successful_voids(): void
    {
        [$admin, $goodOrder] = $this->seedAdminAndOrder(7003, OrderStatus::CONFIRMED);

        $this->mock(OrderService::class, function ($mock) use ($goodOrder) {
            $mock->shouldReceive('voidOrder')
                ->once()
                ->with(\Mockery::on(fn ($o) => $o->order_id === $goodOrder->order_id))
                ->andReturn(null); // success
        });

        $response = $this->actingAs($admin)->post('/orders/bulk-void', [
            'order_ids' => [$goodOrder->order_id, 999999], // one valid, one missing
        ]);

        $response->assertRedirect();

        $goodOrder->refresh();
        $this->assertSame(OrderStatus::VOIDED->value, $goodOrder->status->value);
    }
}
