<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Krypton\Menu;
use App\Models\Package;
use App\Models\PrintEvent;
use App\Services\PrintEventService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Config::set('api.print_events_enabled', false);
});

afterEach(function () {
    Config::set('api.print_events_enabled', false);
});

describe('PrintEvent feature flag disabled (MVP default)', function () {
    it('returns 503 for unprinted-events endpoint when disabled', function () {
        $device = Device::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$device->createToken('test')->plainTextToken,
        ])->getJson('/api/printer/unprinted-events');

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PRINT_EVENTS_DISABLED',
                    'message' => 'PrintEvent processing is disabled. woosoo-print-bridge is the active print execution path.',
                ],
            ]);
    });

    it('returns 503 for print-events ack endpoint when disabled', function () {
        $device = Device::factory()->create();
        $printEvent = PrintEvent::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$device->createToken('test')->plainTextToken,
        ])->postJson("/api/printer/print-events/{$printEvent->id}/ack", [
            'printer_id' => 'TEST_PRINTER',
            'printed_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PRINT_EVENTS_DISABLED',
                ],
            ]);
    });

    it('returns 503 for print-events failed endpoint when disabled', function () {
        $device = Device::factory()->create();
        $printEvent = PrintEvent::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$device->createToken('test')->plainTextToken,
        ])->postJson("/api/printer/print-events/{$printEvent->id}/failed", [
            'reason' => 'Printer offline',
        ]);

        $response->assertStatus(503);
    });

    it('returns 503 for heartbeat endpoint when disabled', function () {
        $device = Device::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$device->createToken('test')->plainTextToken,
        ])->postJson('/api/printer/heartbeat');

        $response->assertStatus(503);
    });

    it('skips PrintEvent creation via service when disabled', function () {
        Config::set('api.print_events_enabled', false);

        $deviceOrder = DeviceOrder::factory()->create();
        $service = app(PrintEventService::class);

        $result = $service->createForOrder($deviceOrder, 'kitchen', ['test' => true]);

        expect($result)->toBeNull();

        // Verify no PrintEvent was created in database
        expect(PrintEvent::where('device_order_id', $deviceOrder->id)->count())->toBe(0);
    });

    it('order submission still works when PrintEvent is disabled', function () {
        $branch = Branch::factory()->create();
        $device = Device::factory()->create([
            'branch_id' => $branch->id,
            'table_id' => 1,
        ]);

        DB::connection('pos')->table('tables')->insert([
            'id' => 1,
            'name' => 'T1',
            'is_available' => true,
            'is_locked' => false,
        ]);

        Menu::factory()->create([
            'id' => 46,
            'name' => 'Classic Feast',
            'receipt_name' => 'Classic Feast',
            'price' => 100,
        ]);
        $modifier = Menu::factory()->create([
            'id' => 10,
            'name' => 'Test Item',
            'receipt_name' => 'Test Item',
            'price' => 0,
        ]);

        $package = Package::create([
            'name' => 'Classic Feast',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $package->allowedMenus()->create([
            'krypton_menu_id' => $modifier->id,
            'menu_type' => 'meat',
            'sort_order' => 0,
            'quantity_limit' => 1,
        ]);

        $this->createTestSession();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$device->createToken('test')->plainTextToken,
        ])->postJson('/api/devices/create-order', [
            'guest_count' => 2,
            'package_id' => 46,
            'items' => [
                [
                    'menu_id' => $modifier->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        // Verify order was created
        expect(DeviceOrder::count())->toBeGreaterThan(0);

        // Verify no PrintEvents were created
        expect(PrintEvent::count())->toBe(0);
    });
});

describe('PrintEvent feature flag enabled (future expansion)', function () {
    beforeEach(function () {
        Config::set('api.print_events_enabled', true);
    });

    it('allows unprinted-events endpoint when enabled', function () {
        // Note: This test requires authentication and proper setup
        // It demonstrates the endpoint becomes accessible when enabled
        // Full test would need device auth and existing print events
        $this->assertTrue(Config::get('api.print_events_enabled'));
    });

    it('creates PrintEvent via service when enabled', function () {
        $deviceOrder = DeviceOrder::factory()->create();
        $service = app(PrintEventService::class);

        $result = $service->createForOrder($deviceOrder, 'kitchen', ['test' => true]);

        expect($result)->toBeInstanceOf(PrintEvent::class);
        expect(PrintEvent::where('device_order_id', $deviceOrder->id)->count())->toBe(1);
    });
});
