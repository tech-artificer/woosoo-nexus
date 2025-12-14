<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Services\Krypton\KryptonContextService;
use App\Enums\OrderStatus;

class OrderCreateAndRefillTest extends TestCase
{
    use RefreshDatabase;

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_manual_order_then_refill_creates_similar_device_order_items()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Refill Device',
            'ip_address' => '127.0.0.1',
            'is_active' => true,
            'table_id' => 1,
        ]);

        // Create a pre-existing device order (represents initial create-order)
        $deviceOrder = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 1001,
            'order_number' => 'ORD-1001-1001',
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        // Add an initial item that would have come from order creation
        DeviceOrderItems::create([
            'order_id' => $deviceOrder->id,
            'menu_id' => 46,
            'ordered_menu_id' => 46,
            'quantity' => 1,
            'price' => 399.00,
            'subtotal' => 399.00,
            'tax' => 39.90,
            'total' => 438.90,
            'notes' => 'Initial',
            'seat_number' => 1,
            'index' => 1,
        ]);

        // Mock KryptonContextService for employee_log_id
        $kctxMock = Mockery::mock(KryptonContextService::class);
        $kctxMock->shouldReceive('getData')->andReturn(['employee_log_id' => 12]);
        $this->app->instance(KryptonContextService::class, $kctxMock);

        // Mock POS connection like in OrderRefillTest
        $qb = Mockery::mock();
        $qb->shouldReceive('insertGetId')->andReturn(9002);
        $qb->shouldReceive('where')->andReturnSelf();
        $qb->shouldReceive('whereIn')->andReturnSelf();
        $qb->shouldReceive('delete')->andReturn(true);
        $qb->shouldReceive('first')->andReturn((object)[
            'id' => 9002,
            'order_id' => 1001,
            'menu_id' => 46,
            'quantity' => 2,
            'employee_log_id' => 12,
            'unit_price' => 399.00,
        ]);

        $posConn = Mockery::mock();
        $posConn->shouldReceive('table')->with('ordered_menus')->andReturn($qb);

        $realDb = DB::getFacadeRoot();
            DB::shouldReceive('getDefaultConnection')->andReturn('testing');
        DB::shouldReceive('connection')->andReturnUsing(function ($name = null) use ($posConn, $realDb) {
            if ($name === 'pos') {
                return $posConn;
            }

            return $realDb->connection($name);
        });

        // Authenticate as device
        $token = $device->createToken('test-token')->plainTextToken;

        $payload = [
            'items' => [
                [
                    'menu_id' => 46,
                    'name' => 'Classic Feast',
                    'quantity' => 2,
                    'price' => 399.00,
                    'index' => 2,
                    'seat_number' => 1,
                    'note' => 'Refill',
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/order/1001/refill', $payload);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Now ensure there are two device_order_items for this device order
        $this->assertDatabaseCount('device_order_items', 2);

        $this->assertDatabaseHas('device_order_items', [
            'order_id' => $deviceOrder->id,
            'menu_id' => 46,
            'ordered_menu_id' => 46,
            'quantity' => 2,
            'price' => 399.00,
        ]);
    }
}
