<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MocksKryptonSession;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\PrintEvent;
use App\Http\Resources\DeviceOrderResource;
use Carbon\Carbon;

class AdminPrintedTimestampTest extends TestCase
{
    use RefreshDatabase, MocksKryptonSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockActiveKryptonSession();
    }

    /**
     * Test that an order printed shortly after creation records the actual
     * printer ACK time, not an artificial delayed timestamp.
     */
    public function test_printed_at_reflects_actual_print_time_close_to_order_creation()
    {
        $branch = \App\Models\Branch::create(['name' => 'Test Branch', 'location' => 'Test']);
        $device = Device::create(['name' => 'test-device', 'ip_address' => '127.0.0.1', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $sessionId = $this->createTestSession();

        $orderPlacedAt = Carbon::parse('2026-05-16 20:00:00', config('app.timezone', 'Asia/Manila'));
        $actualPrintedAt = $orderPlacedAt->copy()->addSeconds(15);
        $actualPrintedAtUtc = $actualPrintedAt->copy()->utc();

        $order = DeviceOrder::create([
            'order_id' => 12345,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 2,
            'total' => 100,
            'subtotal' => 100,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
            'created_at' => $orderPlacedAt,
            'updated_at' => $orderPlacedAt,
        ]);

        // Create a print event
        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/print-events/' . $evt->id . '/ack', [
                'printer_id' => 'PR-TEST-01',
                'printed_at' => $actualPrintedAtUtc->toIso8601String(),
                'verification_mode' => 'connected_only',
            ]);

        $resp->assertStatus(200);

        // Refresh the order
        $order->refresh();

        // Assert printed_at is set to the printer ACK timestamp.
        $this->assertNotNull($order->printed_at);
        $this->assertTrue($order->is_printed);

        $storedPrintedAt = $order->printed_at->copy()->utc();
        $printedAtDriftSeconds = abs($storedPrintedAt->diffInSeconds($actualPrintedAt));

        $this->assertLessThanOrEqual(1, $printedAtDriftSeconds,
            "printed_at should equal the actual printer ACK time. " .
            "expected_printed_at_manila={$actualPrintedAt->toIso8601String()}, " .
            "sent_printed_at_utc={$actualPrintedAtUtc->toIso8601String()}, " .
            "stored_printed_at={$storedPrintedAt->toIso8601String()}.");

        // Verify the resource returns the correct timestamps
        $resource = new DeviceOrderResource($order->load('device'));
        $array = $resource->toArray(request());

        $this->assertNotNull($array['printed_at']);
        $this->assertNotNull($array['created_at']);

        // Parse and compare
        $resourcePrintedAt = Carbon::parse($array['printed_at']);
        $resourceCreatedAt = Carbon::parse($array['created_at']);

        $secondsBetweenOrderAndPrint = abs($resourcePrintedAt->diffInSeconds($resourceCreatedAt));

        $this->assertLessThanOrEqual(60, $secondsBetweenOrderAndPrint,
            "created_at and printed_at should be close because orders print when sent to the kitchen. " .
            "created_at={$resourceCreatedAt->toIso8601String()}, " .
            "printed_at={$resourcePrintedAt->toIso8601String()}, " .
            "seconds_between={$secondsBetweenOrderAndPrint}.");
    }

    /**
     * Test that printed_at is correctly formatted as ISO8601 with timezone in the API response.
     */
    public function test_printed_at_in_resource_is_iso8601_with_timezone()
    {
        $branch = \App\Models\Branch::create(['name' => 'TS Branch', 'location' => 'TS']);
        $device = Device::create(['name' => 'ts-device', 'ip_address' => '127.0.0.2', 'branch_id' => $branch->id]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 54321,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 50,
            'subtotal' => 50,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => true,
            'printed_at' => Carbon::now()->subMinutes(5),
            'printed_by' => 'PRINTER-01',
        ]);

        $resource = new DeviceOrderResource($order->load('device'));
        $array = $resource->toArray(request());

        $this->assertNotNull($array['printed_at']);
        
        // Should be ISO8601 format with timezone (Z or +/-HH:MM)
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[Z]|[+-]\d{2}:\d{2}/', $array['printed_at']);

        // Should be able to parse it back
        $parsed = Carbon::parse($array['printed_at']);
        $this->assertInstanceOf(Carbon::class, $parsed);
    }

    /**
     * Test that null printed_at shows "Not printed" state correctly.
     */
    public function test_null_printed_at_shows_not_printed()
    {
        $branch = \App\Models\Branch::create(['name' => 'Null Branch', 'location' => 'NB']);
        $device = Device::create(['name' => 'null-device', 'ip_address' => '127.0.0.3', 'branch_id' => $branch->id]);

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 11111,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 1,
            'total' => 25,
            'subtotal' => 25,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
            'printed_at' => null,
            'printed_by' => null,
        ]);

        $resource = new DeviceOrderResource($order->load('device'));
        $array = $resource->toArray(request());

        $this->assertNull($array['printed_at']);
        $this->assertFalse($array['is_printed']);
    }

    /**
     * Test that UTC timestamps from the client are correctly stored.
     */
    public function test_client_utc_timestamp_is_correctly_stored()
    {
        $branch = \App\Models\Branch::create(['name' => 'UTC Branch', 'location' => 'UTC']);
        $device = Device::create(['name' => 'utc-device', 'ip_address' => '127.0.0.4', 'branch_id' => $branch->id]);
        $token = $device->createToken('device-auth')->plainTextToken;

        $sessionId = $this->createTestSession();

        $order = DeviceOrder::create([
            'order_id' => 22222,
            'device_id' => $device->id,
            'branch_id' => $branch->id,
            'guest_count' => 2,
            'total' => 75,
            'subtotal' => 75,
            'table_id' => 1,
            'terminal_session_id' => 1,
            'session_id' => $sessionId,
            'status' => 'confirmed',
            'is_printed' => false,
        ]);

        $evt = PrintEvent::factory()->create(['device_order_id' => $order->id]);

        // Send timestamp in UTC with Z suffix (what the Flutter app sends)
        $utcNow = Carbon::now()->utc();
        $utcIsoString = $utcNow->toIso8601String(); // e.g., 2026-05-10T12:00:00.000000Z

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/printer/print-events/' . $evt->id . '/ack', [
                'printer_id' => 'PR-UTC-01',
                'printed_at' => $utcIsoString,
            ]);

        $resp->assertStatus(200);

        $order->refresh();

        // printed_at should be set and within 1 minute of the sent timestamp
        $this->assertNotNull($order->printed_at);
        
        $storedPrintedAt = $order->printed_at->utc();
        $diffSeconds = abs($storedPrintedAt->diffInSeconds($utcNow));
        
        $this->assertLessThan(5, $diffSeconds, 
            "UTC timestamp should be stored correctly with minimal drift, " .
            "but difference was {$diffSeconds} seconds");
    }
}
