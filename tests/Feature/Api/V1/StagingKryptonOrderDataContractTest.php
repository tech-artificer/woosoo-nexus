<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderStatus;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\Package;
use App\Services\Krypton\KryptonContextService;
use App\Services\Krypton\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StagingKryptonOrderDataContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_krypton_context_uses_the_active_terminal_session_tuple(): void
    {
        $this->seedKryptonContextRows();

        $data = app(KryptonContextService::class)->getData();

        $this->assertSame(283, $data['session_id']);
        $this->assertSame(292, $data['terminal_session_id']);
        $this->assertSame(284, $data['cash_tray_session_id']);
        $this->assertSame(309, $data['employee_log_id']);
        $this->assertNull($data['server_employee_log_id']);
    }

    public function test_created_order_matches_krypton_metadata_contract(): void
    {
        $this->seedKryptonContextRows();
        $this->seedOrderSupportRows();

        $device = $this->makeStagingDevice();

        $deviceOrder = app(OrderService::class)->processOrder($device, [
            'guest_count' => 1,
            'items' => [
                [
                    'menu_id' => 10,
                    'quantity' => 2,
                    'price' => 88.00,
                    'index' => 1,
                    'seat_number' => 1,
                    'note' => 'Initial',
                ],
            ],
        ]);

        $posOrder = DB::connection('pos')
            ->table('orders')
            ->where('id', $deviceOrder->order_id)
            ->first();

        $this->assertSame(283, $posOrder->session_id);
        $this->assertSame(292, $posOrder->terminal_session_id);
        $this->assertSame(284, $posOrder->cash_tray_session_id);
        $this->assertNull($posOrder->server_employee_log_id);
        $this->assertStringContainsString('device:199', $posOrder->reference);
        $this->assertStringContainsString('ip:192.168.100.51', $posOrder->reference);
    }

    public function test_refill_links_ordered_menu_rows_to_the_existing_order_check(): void
    {
        $this->seedKryptonContextRows();
        $this->seedOrderSupportRows();

        $device = $this->makeStagingDevice();

        DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $device->table_id,
            'terminal_session_id' => 292,
            'session_id' => 283,
            'order_id' => 19644,
            'order_number' => 'ORD-19644',
            'status' => OrderStatus::CONFIRMED->value,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'guest_count' => 1,
        ]);

        DB::connection('pos')->table('order_checks')->insert([
            'id' => 19643,
            'order_id' => 19644,
            'total_amount' => 0,
            'paid_amount' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'subtotal_amount' => 0,
        ]);

        Sanctum::actingAs($device, [], 'device');

        $response = $this->postJson('/api/order/19644/refill', [
            'items' => [
                [
                    'menu_id' => 10,
                    'name' => 'Beef Bulgogi',
                    'quantity' => 1,
                    'price' => 88.00,
                    'index' => 1,
                    'seat_number' => 1,
                    'note' => 'Refill',
                ],
            ],
        ], [
            'X-Idempotency-Key' => 'staging-refill-order-check',
        ]);

        $response->assertOk()->assertJsonPath('created.0.order_check_id', 19643);
    }

    public function test_initial_order_stores_package_menu_id_and_only_selected_modifier_menu_ids(): void
    {
        $this->seedKryptonContextRows();
        $this->seedOrderSupportRows();

        // Classic Feast is itself a Krypton menu row. The tablet may display
        // many possible P/B/C modifiers for it, but the order payload contains
        // only the customer-selected modifier menu IDs.
        DB::connection('pos')->table('menu_groups')->insert([
            'id' => 2,
            'name' => 'Packages',
        ]);

        DB::connection('pos')->table('menus')->insert([
            [
                'id' => 46,
                'name' => 'Classic Feast',
                'receipt_name' => 'Classic Feast',
                'price' => 399.00,
                'menu_group_id' => 2,
            ],
            [
                'id' => 11,
                'name' => 'Unselected Pork P1',
                'receipt_name' => 'P1',
                'price' => 88.00,
                'menu_group_id' => 1,
            ],
            [
                'id' => 13,
                'name' => 'Chicken Galbi',
                'receipt_name' => 'Chicken Galbi',
                'price' => 92.00,
                'menu_group_id' => 1,
            ],
        ]);

        $package = Package::create([
            'name' => 'Classic Feast',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $package->modifiers()->createMany([
            ['krypton_menu_id' => 10, 'sort_order' => 1],
            ['krypton_menu_id' => 13, 'sort_order' => 2],
        ]);

        $device = $this->makeStagingDevice();

        $deviceOrder = app(OrderService::class)->processOrder($device, [
            'guest_count' => 2,
            'items' => [
                [
                    'menu_id' => 46,
                    'name' => 'Classic Feast',
                    'quantity' => 2,
                    'price' => 1.00,
                    'subtotal' => 2.00,
                    'tax' => 0,
                    'discount' => 0,
                    'note' => null,
                    'is_package' => true,
                    'modifiers' => [
                        ['menu_id' => 10, 'quantity' => 1],
                        ['menu_id' => 13, 'quantity' => 1],
                    ],
                ],
            ],
        ]);

        $rows = DB::table('device_order_items')
            ->where('order_id', $deviceOrder->id)
            ->orderBy('index')
            ->get();

        $this->assertSame([46, 10, 13], $rows->pluck('menu_id')->all());
        $this->assertSame([46, 46, 46], $rows->pluck('ordered_menu_id')->all());
        // Client sent a bogus package price above; POS menu price must win.
        $this->assertSame(399.00, (float) $rows->firstWhere('menu_id', 46)->price);
        $freshOrder = $deviceOrder->fresh();
        $this->assertSame(798.00, (float) $freshOrder->subtotal);
        $this->assertSame(79.80, (float) $freshOrder->tax);
        $this->assertSame(877.80, (float) $freshOrder->total);
        // P1 exists as an available modifier, but was not selected by customer.
        $this->assertNull($rows->firstWhere('menu_id', 11));
    }

    public function test_initial_order_rejects_modifiers_not_allowed_by_active_package_config(): void
    {
        $this->seedKryptonContextRows();
        $this->seedOrderSupportRows();

        DB::connection('pos')->table('menu_groups')->insert([
            'id' => 2,
            'name' => 'Packages',
        ]);

        DB::connection('pos')->table('menus')->insert([
            ['id' => 46, 'name' => 'Classic Feast', 'receipt_name' => 'Classic Feast', 'price' => 399.00, 'menu_group_id' => 2],
            ['id' => 13, 'name' => 'Chicken Galbi', 'receipt_name' => 'Chicken Galbi', 'price' => 92.00, 'menu_group_id' => 1],
        ]);

        $package = Package::create([
            'name' => 'Classic Feast',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $package->modifiers()->create([
            'krypton_menu_id' => 10,
            'sort_order' => 1,
        ]);

        $device = $this->makeStagingDevice();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Modifier 13 is not allowed for package 46');

        app(OrderService::class)->processOrder($device, [
            'guest_count' => 2,
            'items' => [
                [
                    'menu_id' => 46,
                    'name' => 'Classic Feast',
                    'quantity' => 1,
                    'price' => 1.00,
                    'subtotal' => 1.00,
                    'tax' => 0,
                    'discount' => 0,
                    'is_package' => true,
                    'modifiers' => [
                        ['menu_id' => 10, 'quantity' => 1],
                        ['menu_id' => 13, 'quantity' => 1],
                    ],
                ],
            ],
        ]);
    }

    private function makeStagingDevice(): Device
    {
        return Device::create([
            'id'         => 199,
            'name'       => 'Staging Audit Tablet T1',
            'ip_address' => '192.168.100.51',
            'is_active'  => true,
            'table_id'   => 19,
        ]);
    }

    private function seedKryptonContextRows(): void
    {
        Cache::forget('krypton.context');

        DB::connection('pos')->table('terminals')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Main POS Terminal']
        );

        DB::connection('pos')->table('sessions')->insert([
            [
                'id' => 283,
                'status' => 'OPEN',
                'date_time_opened' => now()->subDays(2),
                'date_time_closed' => null,
            ],
            [
                'id' => 308,
                'status' => 'OPEN',
                'date_time_opened' => now(),
                'date_time_closed' => null,
            ],
        ]);

        DB::connection('pos')->table('terminal_sessions')->insert([
            'id' => 292,
            'terminal_id' => 1,
            'session_id' => 283,
            'date_time_opened' => now()->subDays(2),
            'date_time_closed' => null,
        ]);

        DB::connection('pos')->table('employee_logs')->insert([
            'id' => 309,
            'employee_id' => 2,
            'terminal_id' => 1,
            'session_id' => 283,
            'date_time_in' => now()->subDays(2),
            'date_time_out' => null,
        ]);

        DB::connection('pos')->table('cash_tray_sessions')->insert([
            'id' => 284,
            'session_id' => 283,
            'terminal_session_id' => 292,
            'terminal_id' => 1,
            'employee_log_id' => 309,
            'is_open' => true,
        ]);

        DB::connection('pos')->table('terminal_services')->insert([
            'id' => 1,
            'terminal_id' => 1,
            'revenue_id' => 1,
            'service_type_id' => 1,
        ]);

        DB::connection('pos')->table('revenues')->insert([
            'id' => 1,
            'is_active' => true,
            'price_level_id' => 1,
            'tax_set_id' => 1,
        ]);

        app(KryptonContextService::class)->clearCache();
    }

    private function seedOrderSupportRows(): void
    {
        DB::connection('pos')->table('tables')->insert([
            'id' => 19,
            'name' => 'T19',
            'is_available' => true,
            'is_locked' => false,
        ]);

        DB::connection('pos')->table('menu_groups')->insert([
            'id' => 1,
            'name' => 'Meats',
        ]);

        DB::connection('pos')->table('menus')->insert([
            'id' => 10,
            'name' => 'Beef Bulgogi',
            'receipt_name' => 'Beef Bulgogi',
            'price' => 88.00,
            'menu_group_id' => 1,
        ]);
    }
}
