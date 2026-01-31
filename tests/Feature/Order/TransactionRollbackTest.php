<?php

namespace Tests\Feature\Order;

use Tests\TestCase;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Krypton\Order;
use App\Models\Krypton\Menu;
use App\Services\Krypton\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionRollbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed a test menu item for order creation
        Menu::factory()->create([
            'id' => 1,
            'name' => 'Test Menu Item',
            'receipt_name' => 'Test Item',
            'price' => 100.00,
        ]);
    }

    /** @test */
    public function it_rolls_back_entire_transaction_on_order_service_failure()
    {
        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => 1,
        ]);

        // Count initial orders
        $initialPosOrderCount = Order::count();
        $initialDeviceOrderCount = DeviceOrder::count();

        // Attempt to create an order with invalid data that will fail partway through
        $response = $this->actingAs($device, 'device')
            ->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'subtotal' => 100,
                'tax' => 10,
                'discount' => 0,
                'total_amount' => 110,
                'items' => [
                    [
                        'menu_id' => 999999,  // Non-existent menu ID - will cause failure
                        'name' => 'Invalid Item',
                        'quantity' => 1,
                        'price' => 100,
                        'subtotal' => 100,
                        'ordered_menu_id' => null,
                    ],
                ],
            ]);

        // Request should fail
        $this->assertNotEquals(201, $response->status(), 'Order creation should fail with invalid menu_id');

        // Verify NO orders were created in EITHER database (full rollback)
        $this->assertEquals($initialPosOrderCount, Order::count(), 'No POS orders should be created on failure');
        $this->assertEquals($initialDeviceOrderCount, DeviceOrder::count(), 'No device orders should be created on failure');
    }

    /** @test */
    public function it_prevents_partial_writes_on_concurrent_order_creation()
    {
        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => 1,
        ]);

        // Create first order
        $firstOrder = DeviceOrder::factory()->create([
            'device_id' => $device->id,
            'status' => 'confirmed',
            'order_id' => 1,
        ]);

        // Attempt to create second order (should be blocked by lockForUpdate check)
        $response = $this->actingAs($device, 'device')
            ->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'subtotal' => 100,
                'tax' => 10,
                'discount' => 0,
                'total_amount' => 110,
                'items' => [
                    [
                        'menu_id' => 1,
                        'name' => 'Test Item',
                        'quantity' => 1,
                        'price' => 100,
                        'subtotal' => 100,
                        'ordered_menu_id' => null,
                    ],
                ],
            ]);

        // Should return 409 Conflict due to existing order
        $response->assertStatus(409);
        $response->assertJsonStructure(['success', 'message', 'order']);

        // Verify only one device order exists (no duplicate created)
        $this->assertEquals(1, DeviceOrder::where('device_id', $device->id)->count());
    }

    /** @test */
    public function it_ensures_no_orphaned_pos_records_on_local_db_failure()
    {
        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => 1,
        ]);

        $initialPosOrderCount = Order::count();

        // Force local DB failure by making device_orders table unavailable
        // (simulates network partition or local DB crash)
        DB::statement('DROP TABLE IF EXISTS device_orders_backup');
        DB::statement('RENAME TABLE device_orders TO device_orders_backup');

        try {
            $response = $this->actingAs($device, 'device')
                ->postJson('/api/devices/create-order', [
                    'guest_count' => 2,
                    'subtotal' => 100,
                    'tax' => 10,
                    'discount' => 0,
                    'total_amount' => 110,
                    'items' => [
                        [
                            'menu_id' => 1,
                            'name' => 'Test Item',
                            'quantity' => 1,
                            'price' => 100,
                            'subtotal' => 100,
                            'ordered_menu_id' => null,
                        ],
                    ],
                ]);

            // Request should fail with 500 error
            $response->assertStatus(500);
        } finally {
            // Restore table
            DB::statement('RENAME TABLE device_orders_backup TO device_orders');
        }

        // CRITICAL: Verify no orphaned POS orders were created
        // (Transaction should have rolled back everything)
        $this->assertEquals(
            $initialPosOrderCount,
            Order::count(),
            'POS orders should NOT be created when local DB fails (cross-db transaction integrity)'
        );
    }

    /** @test */
    public function it_completes_transaction_atomically_on_success()
    {
        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => 1,
        ]);

        $initialPosOrderCount = Order::count();
        $initialDeviceOrderCount = DeviceOrder::count();

        // Create a valid order
        $response = $this->actingAs($device, 'device')
            ->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'subtotal' => 100,
                'tax' => 10,
                'discount' => 0,
                'total_amount' => 110,
                'items' => [
                    [
                        'menu_id' => 1,
                        'name' => 'Test Item',
                        'quantity' => 1,
                        'price' => 100,
                        'subtotal' => 100,
                        'ordered_menu_id' => null,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['success', 'order']);

        // Verify orders created in BOTH databases (atomic success)
        $this->assertEquals($initialPosOrderCount + 1, Order::count(), 'POS order should be created');
        $this->assertEquals($initialDeviceOrderCount + 1, DeviceOrder::count(), 'Device order should be created');

        // Verify data consistency between POS and local order
        $deviceOrder = DeviceOrder::latest()->first();
        $posOrder = Order::find($deviceOrder->order_id);

        $this->assertNotNull($posOrder, 'POS order should exist');
        $this->assertEquals($posOrder->id, $deviceOrder->order_id, 'Order IDs should match');
        $this->assertEquals($posOrder->guest_count, $deviceOrder->guest_count, 'Guest counts should match');
    }

    /** @test */
    public function it_logs_errors_when_transaction_fails()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Order creation') &&
                       isset($context['device_id']) &&
                       isset($context['error']);
            });

        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => 1,
        ]);

        // Trigger a failure by using invalid menu ID
        $response = $this->actingAs($device, 'device')
            ->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'subtotal' => 100,
                'tax' => 10,
                'discount' => 0,
                'total_amount' => 110,
                'items' => [
                    [
                        'menu_id' => 999999,
                        'name' => 'Invalid Item',
                        'quantity' => 1,
                        'price' => 100,
                        'subtotal' => 100,
                        'ordered_menu_id' => null,
                    ],
                ],
            ]);

        // Error should be logged with full context
        $this->assertNotEquals(201, $response->status());
    }
}
