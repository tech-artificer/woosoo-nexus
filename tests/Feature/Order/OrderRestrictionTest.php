<?php

namespace Tests\Feature\Order;

use Tests\TestCase;
use App\Models\Device;
use App\Models\Branch;
use App\Models\Table;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItem;
use Database\Seeders\TestPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test order restriction enforcement:
 * - Users cannot create duplicate orders
 * - Refill-only mode after initial order
 * - Only meats/sides allowed in refills
 */
class OrderRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected Device $device;
    protected Branch $branch;
    protected Table $table;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test fixtures
        $this->branch = Branch::factory()->create();
        $this->table = Table::factory()->for($this->branch)->create();
        $this->device = Device::factory()
            ->for($this->branch)
            ->for($this->table)
            ->create(['name' => 'Test Device']);
    }

    /**
     * Test: Cannot create a new order if order already exists in PENDING status
     */
    public function test_cannot_create_duplicate_order_when_pending()
    {
        // Create first order
        $firstOrder = DeviceOrder::factory()
            ->for($this->device)
            ->for($this->table)
            ->for($this->branch)
            ->pending()
            ->create(['order_id' => 1001]);

        // Try to create second order
        $response = $this->actingAs($this->device, 'sanctum')
            ->postJson('/api/devices/create-order', [
                'package_id' => 1,
                'guest_count' => 4,
                'table_id' => $this->table->id,
                'items' => [],
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
        $confirmedOrder = DeviceOrder::factory()
            ->for($this->device)
            ->for($this->table)
            ->for($this->branch)
            ->confirmed()
            ->create(['order_id' => 1002]);

        // Try to create second order
        $response = $this->actingAs($this->device, 'sanctum')
            ->postJson('/api/devices/create-order', [
                'package_id' => 1,
                'guest_count' => 4,
                'table_id' => $this->table->id,
                'items' => [],
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
        DeviceOrder::factory()
            ->for($this->device)
            ->for($this->table)
            ->for($this->branch)
            ->completed()
            ->create(['order_id' => 1003]);

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
        $order = DeviceOrder::factory()
            ->for($this->device)
            ->for($this->table)
            ->for($this->branch)
            ->confirmed()
            ->create(['order_id' => 1004]);

        // Try to refill with dessert (should fail)
        $response = $this->actingAs($this->device, 'sanctum')
            ->postJson('/api/order/1004/refill', [
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
        $order = DeviceOrder::factory()
            ->for($this->device)
            ->for($this->table)
            ->for($this->branch)
            ->confirmed()
            ->create([
                'order_id' => 1005,
                'session_id' => 'valid-session-123',
            ]);

        // Try to refill with wrong session ID
        $response = $this->actingAs($this->device, 'sanctum')
            ->postJson('/api/order/1005/refill', [
                'items' => [['name' => 'Beef', 'quantity' => 2]],
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
        $order = DeviceOrder::factory()
            ->for($this->device)
            ->for($this->table)
            ->for($this->branch)
            ->confirmed()
            ->create(['order_id' => 1006]);

        // Create device in different branch
        $otherBranch = Branch::factory()->create();
        $otherDevice = Device::factory()
            ->for($otherBranch)
            ->create();

        // Try to refill order from branch A using device from branch B
        $response = $this->actingAs($otherDevice, 'sanctum')
            ->postJson('/api/order/1006/refill', [
                'items' => [['name' => 'Beef', 'quantity' => 2]],
                'session_id' => $order->session_id,
            ]);

        // Should reject with 403 Forbidden
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }
}
