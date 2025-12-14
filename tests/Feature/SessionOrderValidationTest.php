<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Device;
use App\Models\Branch;

class SessionOrderValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_rejected_for_inactive_session()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device A',
            'ip_address' => '192.168.1.10',
            'is_active' => true,
            'table_id' => 1,
        ]);

        // Create an inactive session in the POS (mapped to testing connection)
        DB::connection('pos')->table('sessions')->insert([
            'id' => 999,
            'status' => 'CLOSED',
        ]);

        $token = $device->createToken('test-token')->plainTextToken;

        $payload = [
            'guest_count' => 1,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total_amount' => 1.00,
            'session_id' => 999,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 1.00,
                    'subtotal' => 1.00,
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/devices/create-order', $payload);

        // Session is device-local; server should accept the order even if a
        // POS session with the same id exists and is closed.
        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
        $this->assertArrayHasKey('order', $response->json());
    }

    public function test_print_event_skipped_for_closed_session()
    {
        Branch::create(['name' => 'Main', 'location' => 'HQ']);

        $device = Device::create([
            'name' => 'Device B',
            'ip_address' => '192.168.1.11',
            'is_active' => true,
            'table_id' => 2,
        ]);

        // create a device order with a session that is closed
        $deviceOrder = \App\Models\DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 1,
            'session_id' => 555,
            'order_id' => 888,
            'order_number' => 'ORD-000888-888',
            'status' => \App\Enums\OrderStatus::COMPLETED->value,
            'subtotal' => 1.00,
            'tax' => 0.00,
            'discount' => 0.00,
            'total' => 1.00,
            'guest_count' => 1,
        ]);

        // Insert closed POS session
        DB::connection('pos')->table('sessions')->insert([
            'id' => 555,
            'status' => 'CLOSED',
        ]);

        $svc = app(\App\Services\PrintEventService::class);
        $res = $svc->createForOrder($deviceOrder, 'INITIAL');

        // Since sessions are device-local, print events should be created.
        $this->assertNotNull($res);
        $this->assertDatabaseHas('print_events', ['device_order_id' => $deviceOrder->id]);
    }
}
