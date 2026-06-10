<?php

use App\Enums\OrderStatus;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Services\Admin\AdminShellBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('counts returns zeros when no orders or devices exist', function () {
    $service = app(AdminShellBadgeService::class);

    $counts = $service->counts();

    expect($counts)->toBe(['orders' => 0, 'devices' => 0]);
});

test('counts orders in pending confirmed and in_progress statuses', function () {
    DeviceOrder::factory()->create(['status' => OrderStatus::PENDING]);
    DeviceOrder::factory()->create(['status' => OrderStatus::CONFIRMED]);
    DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    // Non-pending statuses should NOT be counted
    DeviceOrder::factory()->create(['status' => OrderStatus::COMPLETED]);
    DeviceOrder::factory()->create(['status' => OrderStatus::VOIDED]);
    DeviceOrder::factory()->create(['status' => OrderStatus::READY]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['orders'])->toBe(3);
});

test('counts active devices with null last_seen_at as offline', function () {
    Device::factory()->create(['is_active' => true, 'last_seen_at' => null]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['devices'])->toBe(1);
});

test('counts active devices with last_seen_at older than threshold as offline', function () {
    $threshold = AdminShellBadgeService::OFFLINE_THRESHOLD_MINUTES;

    Device::factory()->create(['is_active' => true, 'last_seen_at' => now()->subMinutes($threshold + 1)]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['devices'])->toBe(1);
});

test('does not count recently seen active devices as offline', function () {
    Device::factory()->create(['is_active' => true, 'last_seen_at' => now()->subMinutes(1)]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['devices'])->toBe(0);
});

test('does not count inactive devices as offline', function () {
    Device::factory()->inactive()->create(['last_seen_at' => null]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['devices'])->toBe(0);
});
