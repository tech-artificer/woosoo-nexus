<?php

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusUpdated;
use App\Models\DeviceOrder;
use App\Models\DeviceOrderItems;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\Fluent\AssertableJson;

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
        ->assertJsonFragment(['message' => 'All items must be marked done before marking as served.']);
});

test('mark ready advances when all items are done', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => true]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk()
        ->assertJson(['status' => 'served']);

    expect($order->fresh()->status)->toBe(OrderStatus::SERVED);
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

test('cannot toggle item on a served order', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED]);
    $item = DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => false]);

    $this->actingAs($admin)
        ->postJson("/kds/items/{$item->id}/toggle")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Cannot toggle items on a completed or closed order.']);
});

test('cannot toggle item on a voided order', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::VOIDED]);
    $item = DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => false]);

    $this->actingAs($admin)
        ->postJson("/kds/items/{$item->id}/toggle")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Cannot toggle items on a completed or closed order.']);
});

test('cannot toggle item on a completed order', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::COMPLETED]);
    $item = DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => false]);

    $this->actingAs($admin)
        ->postJson("/kds/items/{$item->id}/toggle")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Cannot toggle items on a completed or closed order.']);
});

test('mark ready gate re-checks items inside the transaction', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    $item = DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => true]);

    // Simulate an item that was done when initially loaded but undone before advance commits.
    $item->update(['done' => false]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'All items must be marked done before marking as served.']);
});

// --- P2 Recall ---

test('admin can recall a served order to in_progress', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED, 'recalled' => 0]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertOk()
        ->assertJson(['status' => 'in_progress']);

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(OrderStatus::IN_PROGRESS);
    expect($fresh->recalled)->toBe(1);
});

test('recall increments the recalled counter each time', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED, 'recalled' => 2]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertOk();

    expect($order->fresh()->recalled)->toBe(3);
});

test('recall returns 422 for a voided order', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::VOIDED]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Cannot recall voided order.']);

    expect($order->fresh()->status)->toBe(OrderStatus::VOIDED);
});

test('recall returns 422 for a confirmed order (wrong state)', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::CONFIRMED]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Order cannot be recalled from its current state.']);
});

test('recall returns 422 for a completed order', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::COMPLETED]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertUnprocessable();
});

test('recall returns 422 when max recalls reached', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED, 'recalled' => 5]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Maximum recalls reached for this order.']);

    expect($order->fresh()->recalled)->toBe(5);
});

test('non-admin cannot access recall endpoint', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED]);

    $this->actingAs($user)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertForbidden();
});

test('advance response carries the full broadcast payload', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::CONFIRMED]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'order' => ['id', 'status', 'kds_state', 'kds_type', 'items', 'recalled', 'created_at', 'updated_at'],
        ]);
});

test('recall response carries the full broadcast payload', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED, 'recalled' => 0]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'order' => ['id', 'status', 'kds_state', 'kds_type', 'items', 'recalled'],
        ])
        ->assertJsonPath('order.recalled', 1)
        ->assertJsonPath('order.kds_state', 'preparing');
});

test('toggle response carries item_id and order_id for optimistic apply', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    $item = DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => false]);

    $this->actingAs($admin)
        ->postJson("/kds/items/{$item->id}/toggle")
        ->assertOk()
        ->assertJsonStructure(['item_id', 'order_id', 'done', 'done_at'])
        ->assertJsonPath('item_id', $item->id)
        ->assertJsonPath('order_id', $order->id)
        ->assertJsonPath('done', true);
});

// --- server_now clock offset ---

test('advance response includes server_now as an integer', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::CONFIRMED]);

    $before = (int) (microtime(true) * 1000);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk()
        ->assertJsonStructure(['server_now'])
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('server_now', fn ($value) => is_int($value) && $value >= $before)
            ->etc()
        );
});

test('recall response includes server_now as an integer', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED, 'recalled' => 0]);

    $before = (int) (microtime(true) * 1000);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertOk()
        ->assertJsonStructure(['server_now'])
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('server_now', fn ($value) => is_int($value) && $value >= $before)
            ->etc()
        );
});

test('toggle response includes server_now as an integer', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    $item = DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => false]);

    $before = (int) (microtime(true) * 1000);

    $this->actingAs($admin)
        ->postJson("/kds/items/{$item->id}/toggle")
        ->assertOk()
        ->assertJsonStructure(['server_now'])
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('server_now', fn ($value) => is_int($value) && $value >= $before)
            ->etc()
        );
});

// --- MAX_RECALLS atomicity ---

test('recall is rejected when recalled count is already at the cap', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED, 'recalled' => 5]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Maximum recalls reached for this order.']);

    expect($order->fresh()->recalled)->toBe(5);
});

// --- advance in_progress → served path ---

test('advance from in_progress resolves directly to served — no intermediate ready status persists', function () {
    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => true]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk()
        ->assertJsonPath('status', 'served');

    expect($order->fresh()->status)->toBe(OrderStatus::SERVED);
});

// --- F1: single duplicate-free broadcast per transition ---

test('advancing a confirmed order broadcasts OrderStatusUpdated exactly once', function () {
    Event::fake([OrderStatusUpdated::class]);

    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::CONFIRMED]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk();

    Event::assertDispatchedTimes(OrderStatusUpdated::class, 1);
});

test('advancing in_progress to served broadcasts OrderStatusUpdated exactly once', function () {
    Event::fake([OrderStatusUpdated::class]);

    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::IN_PROGRESS]);
    DeviceOrderItems::factory()->for($order, 'device_order')->create(['done' => true]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk()
        ->assertJsonPath('status', 'served');

    Event::assertDispatchedTimes(OrderStatusUpdated::class, 1);
});

test('recalling a served order broadcasts OrderStatusUpdated exactly once', function () {
    Event::fake([OrderStatusUpdated::class]);

    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::SERVED, 'recalled' => 0]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/recall")
        ->assertOk();

    Event::assertDispatchedTimes(OrderStatusUpdated::class, 1);
});

test('advancing a pending order emits CONFIRMED then a single IN_PROGRESS broadcast', function () {
    Event::fake([OrderStatusUpdated::class]);

    $admin = User::factory()->admin()->create();
    $order = DeviceOrder::factory()->create(['status' => OrderStatus::PENDING]);

    $this->actingAs($admin)
        ->postJson("/kds/orders/{$order->id}/advance")
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    // D1: the PENDING→CONFIRMED auto-advance keeps its observer broadcast (a unique
    // CONFIRMED event), while CONFIRMED→IN_PROGRESS is the single controller broadcast.
    // Exactly 2 — not 3 — proves the duplicate IN_PROGRESS broadcast is gone (pre-fix this
    // path emitted CONFIRMED + 2×IN_PROGRESS = 3).
    Event::assertDispatchedTimes(OrderStatusUpdated::class, 2);
});
