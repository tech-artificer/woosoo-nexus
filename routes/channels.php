<?php

use Illuminate\Support\Facades\Broadcast;
use App\Broadcasting\OrderChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Device;

// If no broadcasting driver credentials are present (e.g. during CI/composer install),
// avoid attempting to instantiate the broadcaster (which may construct Pusher)
// by falling back to the null driver. This prevents package discovery failures.
if (empty(config('broadcasting.connections.reverb.key')) && empty(config('broadcasting.connections.pusher.key'))) {
    config(['broadcasting.default' => 'null']);
}

Broadcast::channel('device.{deviceId}', function (Device $device, int $deviceId) {
    return true;
});

Broadcast::channel('orders.{orderId}', function (Device $device, int $orderId) {
    return true;
});

Broadcast::channel('service-requests.{deviceId}', function (User $user, int $deviceId) {
    return true;
});

Broadcast::channel('admin.orders', fn($user) => $user->is_admin);
Broadcast::channel('admin.service-requests', fn($user) => $user->is_admin);
Broadcast::channel('admin.print', true);