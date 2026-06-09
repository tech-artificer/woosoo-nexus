<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use App\Services\Krypton\KryptonContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;

class OrderRefillTest extends TestCase
{
    use MocksKryptonSession, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock active Krypton session for all tests
        $this->mockActiveKryptonSession();

        // Provide an in-memory Krypton menu table for tests that touch the legacy connection.
        Schema::connection('krypton_woosoo')->dropIfExists('menu');
        Schema::connection('krypton_woosoo')->create('menu', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        DB::connection('krypton_woosoo')->table('menu')->insert(['id' => 46, 'name' => 'Classic Feast']);

        // Create menu group for refill validation
        DB::connection('pos')->table('menu_groups')->insert([
            'id' => 1,
            'name' => 'Meats',
        ]);

        // Ensure POS menu exists for refill validation with proper menu_group_id
        DB::connection('pos')->table('menus')->insert([
            'id' => 46,
            'name' => 'Classic Feast',
            'receipt_name' => 'Classic Feast',
            'price' => 399.00,
            'menu_group_id' => 1, // Associate with Meats group
        ]);
    }

    protected function tearDown(): void
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

        $sessionId = $this->createTestSession();

        $deviceOrder = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
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

        // Mock POS connection to accept ordered_menus inserts
        $qb = Mockery::mock();
        $qb->shouldReceive('insertGetId')->andReturn(9001);
        $qb->shouldReceive('where')->andReturnSelf();
        $qb->shouldReceive('whereIn')->andReturnSelf();
        $qb->shouldReceive('delete')->andReturn(true);
        $qb->shouldReceive('first')->andReturn((object) [
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
        DB::shouldReceive('getDefaultConnection')->andReturn('testing');
        DB::shouldReceive('connection')->andReturnUsing(function ($name = null) use ($posConn, $realDb) {
            if ($name === 'pos') {
                return $posConn;
            }

            return $realDb->connection($name);
        });
        DB::shouldReceive('transaction')->andReturnUsing(function ($callback) {
            return $callback();
        });
        DB::shouldReceive('afterCommit')->andReturnUsing(function ($callback) {
            $callback();
        });

        // Authenticate as device using token
        $token = $device->createToken('test-token')->plainTextToken;

        $payload = [
            'items' => [
                [
                    'menu_id' => 46,
                    'name' => 'Classic Feast',
                    'quantity' => 2,
                    'index' => 1,
                    'seat_number' => 1,
                    'note' => 'Refill',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Idempotency-Key' => Str::uuid()->toString(),
        ])->postJson('/api/order/1001/refill', $payload);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // Verify local device_order_items persisted (ordered_menu_id should equal menu_id)
        // Note: order_id column stores DeviceOrder->id, not DeviceOrder->order_id
        $this->assertDatabaseHas('device_order_items', [
            'order_id' => $deviceOrder->id, // Uses DeviceOrder's database id, not POS order_id
            'menu_id' => 46,
            'ordered_menu_id' => 46,
            'quantity' => 2,
            'price' => 399.00,
        ]);
    }

    public function test_refill_accepts_normalized_group_aliases(): void
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Refill Alias Device',
            'ip_address' => '127.0.0.2',
            'is_active' => true,
            'table_id' => 1,
        ]);

        $sessionId = $this->createTestSession();

        $deviceOrder = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'order_id' => 1002,
            'order_number' => 'ORD-1002-1002',
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        $kctxMock = Mockery::mock(KryptonContextService::class);
        $kctxMock->shouldReceive('getData')->andReturn(['employee_log_id' => 12]);
        $this->app->instance(KryptonContextService::class, $kctxMock);

        DB::connection('pos')->table('menu_groups')->insert([
            ['id' => 2, 'name' => 'Meat Order'],
            ['id' => 3, 'name' => 'Side Dishes'],
        ]);

        DB::connection('pos')->table('menus')->insert([
            ['id' => 47, 'name' => 'Marinated Beef', 'receipt_name' => 'Marinated Beef', 'price' => 88.00, 'menu_group_id' => 2],
            ['id' => 48, 'name' => 'Pickled Cucumber', 'receipt_name' => 'Pickled Cucumber', 'price' => 10.00, 'menu_group_id' => 3],
        ]);

        $qb = Mockery::mock();
        $qb->shouldReceive('insertGetId')->andReturn(9002);
        $qb->shouldReceive('where')->andReturnSelf();
        $qb->shouldReceive('whereIn')->andReturnSelf();
        $qb->shouldReceive('delete')->andReturn(true);
        $qb->shouldReceive('first')->andReturn((object) [
            'id' => 9002,
            'order_id' => 1002,
            'menu_id' => 47,
            'quantity' => 1,
            'employee_log_id' => 12,
            'unit_price' => 88.00,
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
        DB::shouldReceive('transaction')->andReturnUsing(function ($callback) {
            return $callback();
        });
        DB::shouldReceive('afterCommit')->andReturnUsing(function ($callback) {
            $callback();
        });

        $token = $device->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/order/1002/refill', [
            'items' => [
                [
                    'menu_id' => 47,
                    'name' => 'Marinated Beef',
                    'quantity' => 1,
                    'index' => 1,
                    'seat_number' => 1,
                    'note' => 'Refill',
                ],
                [
                    'menu_id' => 48,
                    'name' => 'Pickled Cucumber',
                    'quantity' => 1,
                    'index' => 2,
                    'seat_number' => 1,
                    'note' => 'Refill',
                ],
            ],
        ]);

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('device_order_items', [
            'order_id' => $deviceOrder->id,
            'menu_id' => 47,
            'ordered_menu_id' => 47,
        ]);
        $this->assertDatabaseHas('device_order_items', [
            'order_id' => $deviceOrder->id,
            'menu_id' => 48,
            'ordered_menu_id' => 48,
        ]);
    }

    public function test_refill_endpoint_creates_only_one_print_event(): void
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Refill Device',
            'ip_address' => '127.0.0.1',
            'is_active' => true,
            'table_id' => 1,
        ]);

        $sessionId = $this->createTestSession();

        $deviceOrder = DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'order_id' => 2002,
            'order_number' => 'ORD-2002-2002',
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        $kctxMock = Mockery::mock(KryptonContextService::class);
        $kctxMock->shouldReceive('getData')->andReturn(['employee_log_id' => 12]);
        $this->app->instance(KryptonContextService::class, $kctxMock);

        $qb = Mockery::mock();
        $qb->shouldReceive('insertGetId')->andReturn(9003);
        $qb->shouldReceive('where')->andReturnSelf();
        $qb->shouldReceive('whereIn')->andReturnSelf();
        $qb->shouldReceive('delete')->andReturn(true);
        $qb->shouldReceive('first')->andReturn((object) [
            'id' => 9003,
            'order_id' => 2002,
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
        DB::shouldReceive('transaction')->andReturnUsing(function ($callback) {
            return $callback();
        });
        DB::shouldReceive('afterCommit')->andReturnUsing(function ($callback) {
            $callback();
        });

        $token = $device->createToken('test-token')->plainTextToken;

        $payload = [
            'items' => [
                [
                    'menu_id' => 46,
                    'name' => 'Classic Feast',
                    'quantity' => 2,
                    'index' => 1,
                    'seat_number' => 1,
                    'note' => 'Refill',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Idempotency-Key' => Str::uuid()->toString(),
        ])->postJson('/api/order/2002/refill', $payload);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSame(1, PrintEvent::query()->where('device_order_id', $deviceOrder->id)->where('event_type', 'REFILL')->count());
    }
}
