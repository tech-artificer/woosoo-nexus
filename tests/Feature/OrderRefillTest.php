<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Services\Krypton\KryptonContextService;
use App\Enums\OrderStatus;

class OrderRefillTest extends TestCase
{
    use RefreshDatabase;

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_refill_endpoint_persists_items_and_returns_created_payload()
    {
        // Create minimal branch/device/order
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Refill Device',
            'ip_address' => '127.0.0.1',
            'is_active' => true,
            'table_id' => 1,
        ]);

        $deviceOrder = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 1,
            'order_id' => 1001,
            'order_number' => 'ORD-1001-1001',
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        // Mock KryptonContextService to provide an employee_log_id
        $kctxMock = Mockery::mock(KryptonContextService::class);
        $kctxMock->shouldReceive('getData')->andReturn(['employee_log_id' => 12]);
        $this->app->instance(KryptonContextService::class, $kctxMock);

        // No Krypton Menu lookup required — controller accepts `menu_id` + `price` directly.

        // Mock POS connection to accept ordered_menus inserts
        $qb = Mockery::mock();
        $qb->shouldReceive('insertGetId')->andReturn(9001);
        $qb->shouldReceive('where')->andReturnSelf();
        $qb->shouldReceive('whereIn')->andReturnSelf();
        $qb->shouldReceive('delete')->andReturn(true);
        $qb->shouldReceive('first')->andReturn((object)[
            'id' => 9001,
            'order_id' => 1001,
            'menu_id' => 46,
            'quantity' => 2,
            'employee_log_id' => 12,
            'unit_price' => 399.00,
        ]);

        $posConn = Mockery::mock();
        $posConn->shouldReceive('table')->with('ordered_menus')->andReturn($qb);

        $realDb = DB::getFacadeRoot();
        // Ensure getDefaultConnection is available on the DB facade mock used by testing helpers
        DB::shouldReceive('getDefaultConnection')->andReturn('sqlite');
        DB::shouldReceive('connection')->andReturnUsing(function ($name = null) use ($posConn, $realDb) {
            if ($name === 'pos') {
                return $posConn;
            }

            return $realDb->connection($name);
        });

        // Authenticate as device using token
        $token = $device->createToken('test-token')->plainTextToken;

        $payload = [
            'items' => [
                [
                    'menu_id' => 46,
                    'name' => 'Classic Feast',
                    'quantity' => 2,
                    'price' => 399.00,
                    'index' => 1,
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

        // Verify local device_order_items persisted (ordered_menu_id should equal menu_id)
        $this->assertDatabaseHas('device_order_items', [
            'order_id' => $deviceOrder->id,
            'menu_id' => 46,
            'ordered_menu_id' => 46,
            'quantity' => 2,
            'price' => 399.00,
        ]);
    }
}
