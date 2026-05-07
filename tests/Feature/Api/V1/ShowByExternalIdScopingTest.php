<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression tests for OrderApiController::showByExternalId()
 *
 * Root cause: the endpoint previously returned the first row matching
 * external order_id without scoping to the authenticated device.
 *
 * Live example: order_id=19643 had two rows —
 *   id=56 device_id=2 status=completed  (Tablet-02)
 *   id=61 device_id=1 status=confirmed  (Tablet-01)
 *
 * Tablet-01 polling received the completed row (id=56), falsely triggering
 * session-end with the wrong order number.
 */
class ShowByExternalIdScopingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Main', 'location' => 'HQ']);
    }

    private function makeDevice(string $name, int $tableId): Device
    {
        return Device::create([
            'name' => $name,
            'ip_address' => fake()->localIpv4(),
            'is_active' => true,
            'table_id' => $tableId,
            'branch_id' => $this->branch->id,
        ]);
    }

    private function makeOrder(Device $device, int $externalOrderId, string $status, string $orderNumber): DeviceOrder
    {
        return DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'branch_id' => $device->branch_id,
            'session_id' => 1,
            'terminal_session_id' => 1,
            'order_id' => $externalOrderId,
            'order_number' => $orderNumber,
            'status' => $status,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);
    }

    /**
     * Core regression: two devices share the same external order_id.
     * Each device must receive only its own row.
     */
    public function test_each_device_receives_its_own_row_when_external_order_id_is_shared()
    {
        $tablet01 = $this->makeDevice('Tablet-01', 19);
        $tablet02 = $this->makeDevice('Tablet-02', 20);

        $externalOrderId = 19643;

        // Tablet-02's completed row (older, would be returned by .first() without scoping)
        $this->makeOrder($tablet02, $externalOrderId, OrderStatus::COMPLETED->value, 'ORD-20260504-8B8659');

        // Tablet-01's confirmed row (newer, active)
        $activeRow = $this->makeOrder($tablet01, $externalOrderId, OrderStatus::CONFIRMED->value, 'ORD-20260505-CAE474');

        // Tablet-01 requests its order
        Sanctum::actingAs($tablet01, [], 'device');
        $response = $this->getJson("/api/device-order/by-order-id/{$externalOrderId}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('order.id', $activeRow->id)
            ->assertJsonPath('order.order_number', 'ORD-20260505-CAE474')
            ->assertJsonPath('order.status', OrderStatus::CONFIRMED->value);
    }

    /**
     * Mirror of above: Tablet-02 must get its own completed row.
     */
    public function test_second_device_also_receives_its_own_row()
    {
        $tablet01 = $this->makeDevice('Tablet-01', 19);
        $tablet02 = $this->makeDevice('Tablet-02', 20);

        $externalOrderId = 19643;

        $completedRow = $this->makeOrder($tablet02, $externalOrderId, OrderStatus::COMPLETED->value, 'ORD-20260504-8B8659');
        $this->makeOrder($tablet01, $externalOrderId, OrderStatus::CONFIRMED->value, 'ORD-20260505-CAE474');

        Sanctum::actingAs($tablet02, [], 'device');
        $response = $this->getJson("/api/device-order/by-order-id/{$externalOrderId}");

        $response->assertStatus(200)
            ->assertJsonPath('order.id', $completedRow->id)
            ->assertJsonPath('order.order_number', 'ORD-20260504-8B8659')
            ->assertJsonPath('order.status', OrderStatus::COMPLETED->value);
    }

    /**
     * Device that has no row for the given external order_id must get 404.
     * Not another device's row.
     */
    public function test_device_with_no_matching_row_receives_404_not_another_devices_row()
    {
        $tablet01 = $this->makeDevice('Tablet-01', 19);
        $tablet02 = $this->makeDevice('Tablet-02', 20);

        // Only Tablet-02 has an order for this external ID
        $this->makeOrder($tablet02, 99999, OrderStatus::CONFIRMED->value, 'ORD-TABLET02-ONLY');

        Sanctum::actingAs($tablet01, [], 'device');
        $response = $this->getJson('/api/device-order/by-order-id/99999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    /**
     * When same device has multiple rows for the same external order_id
     * (edge case), the newest row by id must be returned.
     */
    public function test_when_same_device_has_multiple_rows_newest_id_wins()
    {
        $tablet = $this->makeDevice('Tablet-01', 19);

        $externalOrderId = 55555;

        $older = $this->makeOrder($tablet, $externalOrderId, OrderStatus::COMPLETED->value, 'ORD-OLDER');
        $newer = $this->makeOrder($tablet, $externalOrderId, OrderStatus::CONFIRMED->value, 'ORD-NEWER');

        // Sanity: newer must have a higher id
        $this->assertGreaterThan($older->id, $newer->id);

        Sanctum::actingAs($tablet, [], 'device');
        $response = $this->getJson("/api/device-order/by-order-id/{$externalOrderId}");

        $response->assertStatus(200)
            ->assertJsonPath('order.id', $newer->id)
            ->assertJsonPath('order.order_number', 'ORD-NEWER');
    }

    /**
     * Unauthenticated request must be rejected.
     */
    public function test_unauthenticated_request_is_rejected()
    {
        $tablet = $this->makeDevice('Tablet-01', 19);
        $this->makeOrder($tablet, 12345, OrderStatus::CONFIRMED->value, 'ORD-12345');

        $response = $this->getJson('/api/device-order/by-order-id/12345');

        $this->assertTrue(in_array($response->getStatusCode(), [401, 403]));
    }
}
