<?php

use Illuminate\Support\Facades\Broadcast;
use App\Broadcasting\OrderChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Device;


Broadcast::channel('orders.{orderId}', function (User $user, int $orderId) {
    return true;
});

Broadcast::channel('service-requests.{deviceId}', function (User $user, int $deviceId) {
    return true;
});

Broadcast::channel('admin.orders', fn($user) => $user->is_admin);
Broadcast::channel('admin.service-requests', fn($user) => $user->is_admin);

// Broadcast::channel('orders', function (User $user, int $deviceId) {
//     return true;
// });

// Broadcast::channel('private-orders.admin', function (User $user, int $deviceId) {  
//     return $user->is_admin;
// });

// Broadcast::channel('channel', function () {
//     // ...
// }, ['guards' => ['device', 'admin']]);
