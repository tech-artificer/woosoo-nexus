<?php

use App\Enums\OrderStatus;
use App\Helpers\OrderBroadcastPayload;
use App\Models\DeviceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Point the Krypton (POS) connection at an unreachable host so any POS query throws.
    Config::set('database.connections.pos', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 1,
        'database' => 'test',
        'username' => 'test',
        'password' => 'test',
        'options' => [PDO::ATTR_TIMEOUT => 1],
    ]);
    DB::purge('pos');
});

test('OrderBroadcastPayload degrades gracefully when POS is unreachable', function () {
    // table_id references a Krypton (POS) table; the connection above cannot reach it.
    $order = DeviceOrder::factory()->create([
        'status' => OrderStatus::READY,
        'table_id' => 1,
    ]);

    // Must not throw even though table/menu live on the unreachable POS connection —
    // a POS outage must never 500 an order broadcast (e.g. KDS advance).
    $payload = OrderBroadcastPayload::make($order);

    expect($payload['status'])->toBe(OrderStatus::READY);
    expect($payload['table'])->toBeNull();
});
