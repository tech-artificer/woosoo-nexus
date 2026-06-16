<?php

use App\Models\Device;
use Illuminate\Support\Facades\Broadcast;

// If no broadcasting driver credentials are present (e.g. during CI/composer install),
// avoid attempting to instantiate the broadcaster (which may construct Pusher)
// by falling back to the null driver. This prevents package discovery failures.
if (empty(config('broadcasting.connections.reverb.key')) && empty(config('broadcasting.connections.pusher.key'))) {
    config(['broadcasting.default' => 'null']);
}

Broadcast::channel('device.{deviceId}', function (Device $device, int $deviceId) {
    // P0 fix 2026-04-07: verify the authenticated device owns this channel
    return (int) $device->id === (int) $deviceId;
});

Broadcast::channel('orders.{orderId}', function (Device $device, int $orderId) {
    // P0 fix 2026-04-07: verify the authenticated device has an order with this POS order_id
    return $device->orders()->where('order_id', $orderId)->exists();
});

Broadcast::channel('service-requests.{orderId}', function (Device $device, int $orderId) {
    // Auth mirrors orders.{orderId}: the authenticated device must own this order.
    // Note: ServiceRequestNotification uses a public Channel, so this callback is
    // retained for consistency and future PrivateChannel promotion.
    return $device->orders()->where('order_id', $orderId)->exists();
});

Broadcast::channel('admin.orders', fn ($user) => $user->is_admin);
Broadcast::channel('admin.service-requests', fn ($user) => $user->is_admin);
