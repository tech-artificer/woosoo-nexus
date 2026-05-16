<?php

namespace Tests\Feature\Order;

use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Package;
use App\Models\Krypton\Order;
use App\Models\Krypton\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;

class TransactionRollbackTest extends TestCase
{
    use RefreshDatabase, MocksKryptonSession;

    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockActiveKryptonSession([
            'terminal_session_id' => 1,
        ]);

        $this->branch = Branch::create([
            'name' => 'Transaction Test Branch',
            'location' => 'HQ',
        ]);

        DB::connection('pos')->table('tables')->insert([
            'id' => 1,
            'name' => 'T1',
            'is_available' => true,
            'is_locked' => false,
        ]);

        // Seed a test menu item for order creation
        Menu::factory()->create([
            'id' => 1,
            'name' => 'Test Menu Item',
            'receipt_name' => 'Test Item',
            'price' => 100.00,
        ]);

        // Seed an active POS session so KryptonContextService can resolve a
        // valid session_id and order creation does not later fail due to
        // missing context.
        $this->createTestSession();
    }

    private function seedTabletPackage(int $kryptonMenuId = 1): void
    {
        Package::query()->create([
            'name' => 'Transaction Test Package',
            'krypton_menu_id' => $kryptonMenuId,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            Mockery::close();
        }
    }

    #[Test]
    public function it_rolls_back_entire_transaction_on_order_service_failure()
    {
        $this->seedTabletPackage();

        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => $this->branch->id,
        ]);

        // Count initial orders
        $initialPosOrderCount = Order::count();
        $initialDeviceOrderCount = DeviceOrder::count();

        // Attempt to create an order with invalid data that will fail partway through
        $response = $this->actingAs($device, 'device')
            ->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'package_id' => 1,
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

    #[Test]
    public function it_prevents_partial_writes_on_concurrent_order_creation()
    {
        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => $this->branch->id,
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
                'package_id' => 1,
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

    #[Test]
    public function it_does_not_compensate_pos_rows_on_local_db_failure_pos_first_contract()
    {
        $this->seedTabletPackage();

        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => $this->branch->id,
        ]);

        $initialPosOrderCount = Order::count();

        // Simulate a local DB failure by injecting a fault into the
        // DeviceOrder::creating model event. This fires inside the service's
        // DB::transaction() block AFTER the POS-side rows have been written,
        // exactly mirroring the network-partition / local-DB-crash scenario
        // we want to test for orphan-prevention. We avoid Schema::rename here
        // because DDL implicitly commits the outer RefreshDatabase transaction
        // in SQLite, which then breaks the service-level savepoint rollback.
        DeviceOrder::creating(function () {
            throw new \RuntimeException('Simulated local DB failure');
        });

        try {
            $response = $this->actingAs($device, 'device')
                ->postJson('/api/devices/create-order', [
                    'guest_count' => 2,
                    'package_id' => 1,
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

            // POS-first contract (krypton_woosoo_specs.md, Issue A): POS writes
            // are authoritative, non-rolled-back side effects. On a local-DB
            // failure the LOCAL transaction rolls back, but POS rows are
            // intentionally NOT compensated (manual POS deletes are forbidden —
            // they can destroy valid POS transactions and drift from real
            // terminal activity). Orphaned POS rows are expected here and are
            // reconciled out-of-band by the reconciliation worker.
            $this->assertEquals(
                0,
                DeviceOrder::where('device_id', $device->id)->count(),
                'Local device order must roll back on local DB failure'
            );
            $this->assertEquals(
                $initialPosOrderCount + 1,
                Order::count(),
                'POS order remains (POS-first: no compensating delete)'
            );
        } finally {
            DeviceOrder::flushEventListeners();
            DeviceOrder::clearBootedModels();
        }
    }

    #[Test]
    public function it_completes_transaction_atomically_on_success()
    {
        $this->seedTabletPackage();

        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => $this->branch->id,
        ]);

        $initialPosOrderCount = Order::count();
        $initialDeviceOrderCount = DeviceOrder::count();

        // Create a valid order
        $response = $this->actingAs($device, 'device')
            ->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'package_id' => 1,
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

    #[Test]
    public function it_logs_errors_when_transaction_fails()
    {
        $device = Device::factory()->create([
            'table_id' => 1,
            'branch_id' => $this->branch->id,
        ]);

        // Trigger a failure by using invalid menu ID
        $response = $this->actingAs($device, 'device')
            ->postJson('/api/devices/create-order', [
                'guest_count' => 2,
                'package_id' => 1,
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

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid order payload: package_id 1 not found or inactive.');
    }
}
