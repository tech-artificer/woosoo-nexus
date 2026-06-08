<?php

use App\Enums\OrderStatus;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\User;

test('admins can advance a confirmed order to in_progress', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::CONFIRMED]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk()
        ->assertJson(['status' => 'in_progress']);

    expect($order->fresh()->status)->toBe(OrderStatus::IN_PROGRESS);
});

test('mark ready is gated when items are not all done', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => false]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'All items must be marked done before advancing to Ready.']);
});

test('mark ready advances when all items are done', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => true]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk()
        ->assertJson(['status' => 'ready']);

    expect($order->fresh()->status)->toBe(OrderStatus::READY);
});

test('admins can toggle an item done flag', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    $item = DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => false]);

    $this->actingAs($admin)
        ->postJson("/kds/items/{$item->id}/toggle")
        ->assertOk()
        ->assertJson(['done' => true]);

    expect($item->fresh()->done)->toBeTrue();
});

test('toggling a done item marks it undone', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    $item = DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => true]);

    $this->actingAs($admin)
        ->postJson("/kds/items/{$item->id}/toggle")
        ->assertOk()
        ->assertJson(['done' => false]);

    expect($item->fresh()->done)->toBeFalse();
    expect($item->fresh()->done_at)->toBeNull();
});

test('non-admin cannot access kds advance endpoint', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::CONFIRMED]);

    $this->actingAs($user)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertForbidden();
});
