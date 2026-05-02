<?php

use App\Enums\OrderStatus;
use App\Models\DeviceOrder;
use App\Models\User;

test('monitoring metrics counts lowercase pending unprinted orders', function () {
    $admin = User::factory()->admin()->create();

    $order = DeviceOrder::factory()->create([
        'status' => OrderStatus::PENDING,
        'is_printed' => false,
        'created_at' => now()->subMinutes(11),
    ]);

    $response = $this
        ->actingAs($admin)
        ->get('/monitoring/metrics');

    $response->assertOk();

    $orderNumbers = collect($response->json('unprintedOrders.items'))
        ->pluck('order_number')
        ->filter()
        ->values();

    expect($orderNumbers)->toContain($order->order_number);
});
