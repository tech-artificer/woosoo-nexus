<?php

use App\Enums\OrderStatus;
use App\Models\Device;
use App\Models\DeviceOrder;
use App\Services\Admin\AdminShellBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('counts returns expected keys with integer values', function () {
    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts)->toHaveKeys(['orders', 'devices']);
    expect($counts['orders'])->toBeInt()->toBeGreaterThanOrEqualTo(0);
    expect($counts['devices'])->toBeInt()->toBeGreaterThanOrEqualTo(0);
});

test('counts orders in pending confirmed and in_progress statuses', function () {
    $device = Device::factory()->create();
    $baseline = app(AdminShellBadgeService::class)->counts()['orders'];

    DeviceOrder::factory()->create(['status' => OrderStatus::PENDING, 'device_id' => $device->id]);
    DeviceOrder::factory()->create(['status' => OrderStatus::CONFIRMED, 'device_id' => $device->id]);
    DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS, 'device_id' => $device->id]);
    // Non-active statuses should NOT be counted
    DeviceOrder::factory()->create(['status' => OrderStatus::COMPLETED, 'device_id' => $device->id]);
    DeviceOrder::factory()->create(['status' => OrderStatus::VOIDED, 'device_id' => $device->id]);
    DeviceOrder::factory()->create(['status' => OrderStatus::READY, 'device_id' => $device->id]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['orders'])->toBe($baseline + 3);
});

test('counts active devices with null last_seen_at as offline', function () {
    $baseline = app(AdminShellBadgeService::class)->counts()['devices'];

    Device::factory()->create(['is_active' => true, 'last_seen_at' => null]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['devices'])->toBe($baseline + 1);
});

test('counts active devices with last_seen_at older than threshold as offline', function () {
    $threshold = AdminShellBadgeService::OFFLINE_THRESHOLD_MINUTES;
    $baseline = app(AdminShellBadgeService::class)->counts()['devices'];

    Device::factory()->create(['is_active' => true, 'last_seen_at' => now()->subMinutes($threshold + 1)]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['devices'])->toBe($baseline + 1);
});

test('does not count recently seen active devices as offline', function () {
    $baseline = app(AdminShellBadgeService::class)->counts()['devices'];

    Device::factory()->create(['is_active' => true, 'last_seen_at' => now()->subMinutes(1)]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['devices'])->toBe($baseline);
});

test('does not count inactive devices as offline', function () {
    $baseline = app(AdminShellBadgeService::class)->counts()['devices'];

    Device::factory()->inactive()->create(['last_seen_at' => null]);

    $counts = app(AdminShellBadgeService::class)->counts();

    expect($counts['devices'])->toBe($baseline);
});
