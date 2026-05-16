<?php

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertStatus(200);
});

test('dashboard index keeps query count flat when device count grows', function () {
    $sessionId = $this->createTestSession();

    $branch = Branch::create([
        'name' => 'Dashboard Branch',
        'location' => 'HQ',
    ]);

    $seedRow = function (int $tableId) use ($branch, $sessionId): void {
        DB::connection('pos')->table('tables')->insert([
            'id' => $tableId,
            'name' => "Table {$tableId}",
            'is_available' => true,
            'is_locked' => false,
        ]);

        $device = Device::create([
            'name' => "Dashboard Device {$tableId}",
            'ip_address' => "127.0.0.{$tableId}",
            'is_active' => true,
            'table_id' => $tableId,
            'branch_id' => $branch->id,
        ]);

        DeviceOrder::create([
            'device_id' => $device->id,
            'table_id' => $tableId,
            'branch_id' => $branch->id,
            'session_id' => $sessionId,
            'order_id' => 1000 + $tableId,
            'order_number' => "ORD-{$tableId}",
            'status' => OrderStatus::PENDING->value,
            'subtotal' => 10.00,
            'tax' => 1.00,
            'discount' => 0.00,
            'total' => 11.00,
            'guest_count' => 2,
                'created_at' => now()->subMinutes($tableId),
                'updated_at' => now()->subMinutes($tableId),
            ]);
    };

    $seedRow(1);

    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->actingAs($admin);

    $measureQueryCount = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        DB::connection('pos')->flushQueryLog();
        DB::connection('pos')->enableQueryLog();

        $this->get('/dashboard')->assertOk();

        return count(DB::getQueryLog()) + count(DB::connection('pos')->getQueryLog());
    };

    $baselineQueryCount = $measureQueryCount();

    collect(range(2, 5))->each($seedRow);

    $expandedQueryCount = $measureQueryCount();

    expect($expandedQueryCount - $baselineQueryCount)->toBeLessThanOrEqual(2);
});
