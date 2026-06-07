<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\Package;
use App\Services\Krypton\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;

class DeviceOrderIntentPayloadHardeningTest extends TestCase
{
    use MocksKryptonSession, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockActiveKryptonSession();
    }

    public function test_create_order_strips_client_fields_before_order_service(): void
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        Package::query()->create([
            'name' => 'Classic Feast',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $device = Device::create([
            'name' => 'Intent Hardening Device',
            'ip_address' => '192.168.100.8',
            'is_active' => true,
            'table_id' => 10,
        ]);

        $sessionId = $this->createTestSession();
        $capturedPayload = null;

        $this->mock(OrderService::class, function ($mock) use ($device, $sessionId, &$capturedPayload) {
            $mock->shouldReceive('processOrder')
                ->once()
                ->andReturnUsing(function ($deviceArg, array $payload) use ($device, $sessionId, &$capturedPayload) {
                    $capturedPayload = $payload;

                    $deviceOrder = DeviceOrder::create([
                        'device_id' => $device->id,
                        'table_id' => $device->table_id,
                        'terminal_session_id' => 1,
                        'session_id' => $sessionId,
                        'order_id' => 34567,
                        'order_number' => 'ORD-000001-34567',
                        'status' => OrderStatus::CONFIRMED->value,
                        'subtotal' => 399.00,
                        'tax' => 39.90,
                        'discount' => 0.00,
                        'total' => 438.90,
                        'guest_count' => 2,
                    ]);

                    DeviceOrderItems::create([
                        'order_id' => $deviceOrder->id,
                        'menu_id' => 46,
                        'quantity' => 2,
                        'price' => 399.00,
                        'subtotal' => 798.00,
                        'tax' => 79.80,
                        'total' => 877.80,
                    ]);

                    return $deviceOrder;
                });
        });

        $token = $device->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Idempotency-Key' => Str::uuid()->toString(),
        ])->postJson('/api/devices/create-order', [
            'guest_count'  => 2,
            'package_id'   => 46,
            'subtotal'     => 1.00,
            'tax'          => 0.10,
            'discount'     => 0.50,
            'total_amount' => 0.60,
            'session_id'   => 999,
            'items'        => [
                [
                    'menu_id'         => 10,
                    'quantity'        => 2,
                    'name'            => 'Tampered Item',
                    'price'           => 0.01,
                    'subtotal'        => 0.02,
                    'ordered_menu_id' => 12345,
                    'modifiers'       => [
                        ['menu_id' => 99, 'quantity' => 1],
                    ],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertIsArray($capturedPayload);
        $this->assertSame(2, $capturedPayload['guest_count']);
        $this->assertSame(46, $capturedPayload['package_id']);
        $this->assertArrayNotHasKey('subtotal', $capturedPayload);
        $this->assertArrayNotHasKey('tax', $capturedPayload);
        $this->assertArrayNotHasKey('discount', $capturedPayload);
        $this->assertArrayNotHasKey('total_amount', $capturedPayload);
        $this->assertArrayNotHasKey('session_id', $capturedPayload);

        $this->assertCount(1, $capturedPayload['items']);
        $this->assertTrue($capturedPayload['items'][0]['is_package']);
        $this->assertSame(46, $capturedPayload['items'][0]['menu_id']);
        $this->assertSame(2, $capturedPayload['items'][0]['quantity']);
        $this->assertSame(
            [['menu_id' => 10, 'quantity' => 2]],
            $capturedPayload['items'][0]['modifiers']
        );

        $persisted = DeviceOrder::query()->where('device_id', $device->id)->first();
        $this->assertNotNull($persisted);
        $this->assertSame(399.00, (float) $persisted->subtotal);
        $this->assertSame(438.90, (float) $persisted->total);
        $this->assertSame(2, $persisted->guest_count);
    }
}
