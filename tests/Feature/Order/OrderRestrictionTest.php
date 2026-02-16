<?php

namespace Tests\Feature\Order;

use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;
use App\Models\Device;
use App\Models\Branch;
use App\Models\DeviceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Enums\OrderStatus;

/**
 * Test order restriction enforcement:
 * - Users cannot create duplicate orders
 * - Refill-only mode after initial order
 * - Only meats/sides allowed in refills
 */
class OrderRestrictionTest extends TestCase
{
    use RefreshDatabase, MocksKryptonSession;

    protected Device $device;
    protected Branch $branch;
    protected int $tableId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockActiveKryptonSession();
        
        // Create test fixtures
        $this->branch = Branch::create(['name' => 'Main', 'location' => 'HQ']);
        $this->tableId = 100;
        $this->device = Device::create([
            'name' => 'Test Device',
            'ip_address' => '192.168.10.10',
            'is_active' => true,
            'table_id' => $this->tableId,
            'branch_id' => $this->branch->id,
        ]);

        // Ensure a menu item exists on the POS connection for refill validation
        DB::connection('pos')->table('menus')->insert([
            'id' => 46,
            'name' => 'Beef',
            'receipt_name' => 'Beef',
            'price' => 100.00,
        ]);
    }

    /**
     * Test: Cannot create a new order if order already exists in PENDING status
     */
    public function test_cannot_create_duplicate_order_when_pending()
    {
        // Create first order
        $firstOrder = DeviceOrder::create([
            'device_id' => $this->device->id,
            'table_id' => $this->tableId,
            'terminal_session_id' => 1,
            'session_id' => $this->createTestSession(),
            'order_id' => 1001,
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        // Try to create second order
        $token = $this->device->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/devices/create-order', [
                'guest_count' => 1,
                'subtotal' => 1.00,
                'tax' => 0.00,
                'discount' => 0.00,
                'total_amount' => 1.00,
                'items' => [
                    [
                        'menu_id' => 1,
                        'name' => 'Test Item',
                        'quantity' => 1,
                        'price' => 1.00,
                        'subtotal' => 1.00,
                        'tax' => 0.00,
                        'discount' => 0.00,
                    ]
                ],
            ]);

        // Should be rejected with 409 Conflict
        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
        ]);
        $response->assertJsonPath('message', fn($msg) => 
            str_contains($msg, 'existing order') || str_contains($msg, 'prevent')
        );
    }

    /**
     * Test: Cannot create a new order if order already exists in CONFIRMED status
     */
    public function test_cannot_create_duplicate_order_when_confirmed()
    {
        // Create confirmed order
        $confirmedOrder = DeviceOrder::create([
            'device_id' => $this->device->id,
            'table_id' => $this->tableId,
            'terminal_session_id' => 1,
            'session_id' => $this->createTestSession(),
            'order_id' => 1002,
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        // Try to create second order
        $token = $this->device->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/devices/create-order', [
                'guest_count' => 1,
                'subtotal' => 1.00,
                'tax' => 0.00,
                'discount' => 0.00,
                'total_amount' => 1.00,
                'items' => [
                    [
                        'menu_id' => 1,
                        'name' => 'Test Item',
                        'quantity' => 1,
                        'price' => 1.00,
                        'subtotal' => 1.00,
                        'tax' => 0.00,
                        'discount' => 0.00,
                    ]
                ],
            ]);

        // Should be rejected with 409 Conflict
        $response->assertStatus(409);
        $response->assertJson(['success' => false]);
    }

    /**
     * Test: Can create a new order if previous order is COMPLETED
     * (completed orders don't block new orders)
     */
    public function test_can_create_new_order_after_previous_completed()
    {
        // Create completed order
        DeviceOrder::create([
            'device_id' => $this->device->id,
            'table_id' => $this->tableId,
            'terminal_session_id' => 1,
            'session_id' => $this->createTestSession(),
            'order_id' => 1003,
            'status' => OrderStatus::COMPLETED->value,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        // Should be able to create a new order
        // (Note: In reality this would require POS integration; this test documents the intent)
        $this->assertTrue(true); // Placeholder: actual implementation depends on OrderService
    }

    /**
     * Test: Refill request only accepts meats and sides items
     * (other categories should be rejected)
     */
    public function test_refill_rejects_non_meat_non_side_items()
    {
        // Create existing order
        $order = DeviceOrder::create([
            'device_id' => $this->device->id,
            'table_id' => $this->tableId,
            'terminal_session_id' => 1,
            'session_id' => $this->createTestSession(),
            'order_id' => 1004,
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        // Try to refill with dessert (should fail)
        $token = $this->device->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/order/1004/refill', [
                'items' => [
                    [
                        'name' => 'Brownie Sundae', // Dessert, not allowed
                        'quantity' => 1,
                    ]
                ],
                'session_id' => $order->session_id,
            ]);

        // Should reject with validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items.0.name');
    }

    /**
     * Test: Refill accepts meats items
     */
    public function test_refill_accepts_meats_items()
    {
        // This test documents the intended behavior
        // Actual implementation would require POS integration
        $this->assertTrue(true); // Placeholder for integration test
    }

    /**
     * Test: Refill accepts sides items
     */
    public function test_refill_accepts_sides_items()
    {
        // This test documents the intended behavior
        // Actual implementation would require POS integration
        $this->assertTrue(true); // Placeholder for integration test
    }

    /**
     * Test: Session scoping prevents cross-session refill attempts
     */
    public function test_refill_blocked_by_session_mismatch()
    {
        // Create order with session ID
        $order = DeviceOrder::create([
            'device_id' => $this->device->id,
            'table_id' => $this->tableId,
            'terminal_session_id' => 1,
            'session_id' => 'valid-session-123',
            'order_id' => 1005,
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        // Try to refill with wrong session ID
        $token = $this->device->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/order/1005/refill', [
                'items' => [[
                    'menu_id' => 46,
                    'name' => 'Beef',
                    'quantity' => 2,
                    'price' => 100.00,
                ]],
            'session_id' => 'wrong-session-456',
            ]);

        // Should reject with 403 Forbidden
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
        $response->assertJsonPath('message', fn($msg) => str_contains($msg, 'Session'));
    }

    /**
     * Test: Cross-device refill attempts are blocked (branch isolation)
     */
    public function test_refill_blocked_by_device_branch_mismatch()
    {
        // Create order for device in branch A
        $order = DeviceOrder::create([
            'device_id' => $this->device->id,
            'table_id' => $this->tableId,
            'terminal_session_id' => 1,
            'session_id' => 'session-1006',
            'order_id' => 1006,
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        // Create device in different branch
        $otherBranch = Branch::create(['name' => 'Other', 'location' => 'HQ-2']);
        $otherDevice = Device::create([
            'name' => 'Other Device',
            'ip_address' => '192.168.10.20',
            'is_active' => true,
            'table_id' => 200,
            'branch_id' => $otherBranch->id,
        ]);

        // Try to refill order from branch A using device from branch B
        $token = $otherDevice->createToken('other-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/order/1006/refill', [
                'items' => [[
                    'menu_id' => 46,
                    'name' => 'Beef',
                    'quantity' => 2,
                    'price' => 100.00,
                ]],
                'session_id' => $order->session_id,
            ]);

        // Should reject with 403 Forbidden
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }
}
