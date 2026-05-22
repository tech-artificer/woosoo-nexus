<?php

use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Repositories\Krypton\OrderRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $schema = Schema::connection('pos');

    $schema->dropIfExists('orders');
    $schema->create('orders', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('session_id')->nullable();
        $table->integer('terminal_session_id')->nullable();
        $table->dateTime('date_time_opened')->nullable();
        $table->dateTime('date_time_closed')->nullable();
        $table->integer('revenue_id')->nullable();
        $table->integer('terminal_id')->nullable();
        $table->boolean('is_open')->default(true);
        $table->boolean('is_transferred')->default(false);
        $table->boolean('is_voided')->default(false);
        $table->integer('guest_count')->nullable();
        $table->integer('service_type_id')->nullable();
        $table->boolean('is_available')->default(true);
        $table->string('transaction_no')->nullable();
        $table->integer('terminal_service_id')->nullable();
        $table->integer('reprint_count')->nullable()->default(0);
        $table->dateTime('created_on')->nullable();
    });
});

test('getAllOrdersWithDeviceData hydrates order checks and ordered menus in bulk', function () {
    Branch::create([
        'name' => 'Repository Branch',
        'location' => 'HQ',
    ]);

    DB::connection('pos')->table('tables')->insert([
        ['id' => 1, 'name' => 'Table 1', 'is_available' => true, 'is_locked' => false],
        ['id' => 2, 'name' => 'Table 2', 'is_available' => true, 'is_locked' => false],
        ['id' => 3, 'name' => 'Table 3', 'is_available' => true, 'is_locked' => false],
        ['id' => 4, 'name' => 'Table 4', 'is_available' => true, 'is_locked' => false],
        ['id' => 5, 'name' => 'Table 5', 'is_available' => true, 'is_locked' => false],
    ]);

    $terminalSession = (object) ['id' => 22];
    $session = (object) ['id' => 11];

    $seedRows = function (int $index) use ($terminalSession): void {
        $tableId = $index;

        $device = Device::create([
            'name' => "Repository Device {$index}",
            'ip_address' => "127.0.1.{$index}",
            'is_active' => true,
            'table_id' => $tableId,
        ]);

        DB::connection('pos')->table('orders')->insert([
            'id' => $index,
            'session_id' => 11,
            'terminal_session_id' => $terminalSession->id,
            'date_time_opened' => now(),
            'date_time_closed' => null,
            'revenue_id' => 1,
            'terminal_id' => 1,
            'is_open' => true,
            'is_transferred' => false,
            'is_voided' => false,
            'guest_count' => 2,
            'service_type_id' => 1,
            'is_available' => true,
            'transaction_no' => "TX-{$index}",
            'terminal_service_id' => 1,
            'reprint_count' => 0,
            'created_on' => now()->subMinutes($index),
        ]);

        DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $tableId,
            'terminal_session_id' => $terminalSession->id,
            'session_id' => 11,
            'order_id' => $index,
            'order_number' => "ORD-{$index}",
            'status' => 'confirmed',
            'subtotal' => 10.00,
            'tax' => 1.00,
            'discount' => 0.00,
            'total' => 11.00,
            'guest_count' => 2,
        ]);

        DB::connection('pos')->table('order_checks')->insert([
            'id' => $index,
            'order_id' => $index,
            'subtotal_amount' => 10.00,
            'tax_amount' => 1.00,
            'discount_amount' => 0.00,
            'total_amount' => 11.00,
            'paid_amount' => 0.00,
        ]);

        DB::connection('pos')->table('ordered_menus')->insert([
            ['id' => ($index * 10) + 1, 'order_id' => $index, 'menu_id' => 1, 'price' => 5.00, 'original_price' => 5.00, 'sub_total' => 5.00, 'tax' => 0.50, 'note' => 'A'],
            ['id' => ($index * 10) + 2, 'order_id' => $index, 'menu_id' => 2, 'price' => 5.00, 'original_price' => 5.00, 'sub_total' => 5.00, 'tax' => 0.50, 'note' => 'B'],
        ]);
    };

    $seedRows(1);

    $measureQueryCount = function () use ($session, $terminalSession) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        DB::connection('pos')->flushQueryLog();
        DB::connection('pos')->enableQueryLog();

        $result = OrderRepository::getAllOrdersWithDeviceData([
            'session' => $session,
            'terminalSession' => $terminalSession,
        ]);

        $queryCount = count(DB::getQueryLog()) + count(DB::connection('pos')->getQueryLog());

        return [$result, $queryCount];
    };

    [$baselineResult, $baselineQueryCount] = $measureQueryCount();
    expect($baselineResult)->toHaveCount(1);

    collect(range(2, 5))->each($seedRows);

    [$expandedResult, $expandedQueryCount] = $measureQueryCount();

    expect($expandedResult)->toHaveCount(5);
    expect($expandedQueryCount - $baselineQueryCount)->toBeLessThanOrEqual(2);
    expect($expandedResult->first()->orderCheck)->not->toBeNull();
    expect($expandedResult->first()->orderedMenus)->toHaveCount(2);
});

it('getOpenOrdersForSession returns only open orders for the given session', function () {
    $repo = new OrderRepository();

    DB::connection('pos')->table('orders')->insert([
        ['id' => 10, 'session_id' => 99, 'is_open' => true,  'is_voided' => false, 'is_transferred' => false, 'is_available' => true, 'created_on' => now()],
        ['id' => 11, 'session_id' => 99, 'is_open' => false, 'is_voided' => false, 'is_transferred' => false, 'is_available' => true, 'created_on' => now()],
        ['id' => 12, 'session_id' => 88, 'is_open' => true,  'is_voided' => false, 'is_transferred' => false, 'is_available' => true, 'created_on' => now()],
    ]);

    $result = $repo->getOpenOrdersForSession(99);

    expect($result)->toHaveCount(1);
    expect($result->first()->id)->toBe(10);
});

it('getOpenOrdersForSession returns empty collection for unknown session', function () {
    $repo = new OrderRepository();

    $result = $repo->getOpenOrdersForSession(999);

    expect($result)->toBeEmpty();
});
