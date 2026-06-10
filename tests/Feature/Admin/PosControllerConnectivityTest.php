<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

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

test('pos index returns 200 with posConnected false when pos connection fails', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('POS/Index')
            ->where('posConnected', false)
            ->where('posStatus', 'unreachable')
            ->has('posMessage')
            ->where('terminals', [])
            ->where('currentSession', null)
        );
});

test('pos index reports not_configured when mysql password is empty', function () {
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

    $this->actingAs($admin)
        ->get(route('pos.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('POS/Index')
            ->where('posConnected', false)
            ->where('posStatus', 'not_configured')
            ->where('terminals', [])
        );
});

test('pos terminal tables endpoint returns 503 when pos connection fails', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('pos.terminal.tables', ['terminalId' => '1']))
        ->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('status', 'unreachable');
});

test('pos table orders endpoint returns 503 when pos connection fails', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('pos.table.orders', ['terminalId' => '1', 'tableId' => '1']))
        ->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('status', 'unreachable');
});
