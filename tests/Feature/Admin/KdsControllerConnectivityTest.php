<?php

use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('database.connections.pos', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 1,
        'database' => 'test',
        'username' => 'test',
        'password' => 'test',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'options' => [PDO::ATTR_TIMEOUT => 1],
    ]);
    DB::purge('pos');
});

test('kds index returns 200 with empty tickets when pos connection fails', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    get(route('kds.display'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->where('initialTickets', [])
        );
});

test('kds index falls back to the device name when pos unreachable and active orders exist', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $device = Device::factory()->create(['name' => 'Patio-7']);
    DeviceOrder::factory()->confirmed()->create(['table_id' => null, 'device_id' => $device->id]);

    actingAs($admin);

    get(route('kds.display'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->has('initialTickets', 1)
            ->where('initialTickets.0.table', 'Patio-7')
        );
});

test('kds index falls back to the device name when pos unreachable and an order has a table_id set', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    // table_id set + POS down is the exact case that previously triggered a lazy-load 500:
    // toTicket() must NOT touch the POS-backed device.table/table relations when POS is down,
    // but it must still fall back to the always-loaded device name instead of '—'.
    $device = Device::factory()->create(['name' => 'Patio-8']);
    DeviceOrder::factory()->confirmed()->create(['table_id' => 1, 'device_id' => $device->id]);

    actingAs($admin);

    get(route('kds.display'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->has('initialTickets', 1)
            ->where('initialTickets.0.table', 'Patio-8')
        );
});

test('kds index returns 200 with empty tickets when pos password is not configured', function () {
    Config::set('database.connections.pos', [
        'driver' => 'mysql',
        'host' => '192.168.100.7',
        'port' => 3308,
        'database' => 'krypton_woosoo',
        'username' => 'krypton_readonly',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'options' => [PDO::ATTR_TIMEOUT => 1],
    ]);
    DB::purge('pos');

    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    get(route('kds.display'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->where('initialTickets', [])
        );
});
