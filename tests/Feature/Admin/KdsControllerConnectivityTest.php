<?php

use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\Package;
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

test('kds index returns 200 with tickets when pos unreachable and active orders exist', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    DeviceOrder::factory()->confirmed()->create(['table_id' => null]);

    actingAs($admin);

    get(route('kds.display'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->has('initialTickets', 1)
            ->where('initialTickets.0.table', '—')
        );
});

test('kds index returns 200 when pos unreachable and an order has a table_id set', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    // table_id set + POS down is the exact case that previously triggered a lazy-load 500:
    // toTicket() must NOT touch the POS-backed device.table/table relations when POS is down.
    DeviceOrder::factory()->confirmed()->create(['table_id' => 1]);

    actingAs($admin);

    get(route('kds.display'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->has('initialTickets', 1)
            ->where('initialTickets.0.table', '—')
        );
});

test('kds ticket excludes the package anchor item and surfaces guestCount', function () {
    // Package-anchor filtering keys off menu_id matching a Package's krypton_menu_id —
    // app-DB data, not POS-backed — so it must work even with POS unreachable (forced
    // down by this file's beforeEach). Item display name resolution goes through the
    // POS-backed menu relation and is covered separately where POS is reachable.
    $admin = User::factory()->create(['is_admin' => true]);
    $package = Package::factory()->create();
    $order = DeviceOrder::factory()->confirmed()->create(['table_id' => null, 'guest_count' => 4]);
    DeviceOrderItems::factory()->for($order, 'device_order')
        ->forMenu($package->krypton_menu_id)
        ->create();
    DeviceOrderItems::factory()->for($order, 'device_order')
        ->forMenu(114)
        ->create();

    actingAs($admin);

    get(route('kds.display'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('KDS/Display')
            ->has('initialTickets', 1)
            ->where('initialTickets.0.guestCount', 4)
            ->has('initialTickets.0.items', 1)
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
