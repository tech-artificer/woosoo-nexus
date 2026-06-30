<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V2;

use App\Models\Device;
use App\Models\DeviceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the GET /api/v2/tablet/table/{tableId}/active-order contract:
 * - 204 when no active order exists for the table
 * - 200 with snapshot when an active order exists
 * - 403 when the device is not assigned to the requested table
 * - 401 when unauthenticated
 * - snapshot includes pos_originated: true on all rounds
 */
class TabletActiveOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedDevice(array $attributes = []): Device
    {
        return Device::factory()->create(array_merge(['is_active' => true], $attributes));
    }

    private function deviceToken(Device $device): string
    {
        return $device->createToken('test')->plainTextToken;
    }

    public function test_returns_204_when_no_active_order_for_table(): void
    {
        $device = $this->authenticatedDevice(['table_id' => 5]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/table/5/active-order');

        $response->assertNoContent();
    }

    public function test_returns_200_with_snapshot_when_active_order_exists(): void
    {
        $device = $this->authenticatedDevice(['table_id' => 7]);

        DeviceOrder::factory()->confirmed()->create([
            'table_id' => 7,
            'order_id' => 55001,
            'order_number' => 'ORD-TEST-001',
            'guest_count' => 4,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/table/7/active-order');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.order_id', 55001);
        $response->assertJsonPath('data.guest_count', 4);
        $response->assertJsonPath('data.table_id', 7);
    }

    public function test_returns_403_when_device_not_assigned_to_table(): void
    {
        $device = $this->authenticatedDevice(['table_id' => 10]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/table/99/active-order');

        $response->assertForbidden();
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v2/tablet/table/5/active-order');

        $response->assertUnauthorized();
    }

    public function test_snapshot_rounds_are_marked_pos_originated(): void
    {
        $device = $this->authenticatedDevice(['table_id' => 8]);

        $order = DeviceOrder::factory()->confirmed()->create([
            'table_id' => 8,
            'order_id' => 55002,
        ]);

        // Add an initial item to produce a round in the snapshot
        $order->items()->create([
            'menu_id' => 100,
            'quantity' => 2,
            'price' => 50.00,
            'subtotal' => 100.00,
            'is_refill' => false,
        ]);

        $response = $this->withToken($this->deviceToken($device), 'Bearer')
            ->getJson('/api/v2/tablet/table/8/active-order');

        $response->assertOk();

        $rounds = $response->json('data.rounds');
        $this->assertNotEmpty($rounds);
        foreach ($rounds as $round) {
            $this->assertTrue($round['pos_originated'], 'All snapshot rounds must be marked pos_originated=true');
        }
    }
}
