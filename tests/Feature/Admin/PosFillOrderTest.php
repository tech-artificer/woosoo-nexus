<?php

use App\Enums\OrderStatus;
use App\Events\Order\OrderCompleted;
use App\Models\DeviceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('pos fill-order rejects non-admins (P1-07)', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->postJson('/pos/fill-order', ['order_id' => 1])
        ->assertForbidden();
});

test('pos fill-order returns 404 when the order is not found (P1-07)', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson('/pos/fill-order', ['order_id' => 999999])
        ->assertNotFound()
        ->assertJson(['success' => false]);
});

test('pos fill-order completes an order and dispatches OrderCompleted (P1-07)', function () {
    // Fake all events so the order-status observer broadcast doesn't run during this
    // fill-order happy-path assertion (the broadcast path is covered elsewhere).
    Event::fake();

    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create([
        'order_id' => 8001,
        'status' => OrderStatus::IN_PROGRESS,
    ]);

    $this->actingAs($admin)
        ->postJson('/pos/fill-order', ['order_id' => 8001])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($order->fresh()->status)->toBe(OrderStatus::COMPLETED);
    Event::assertDispatched(OrderCompleted::class);
});
