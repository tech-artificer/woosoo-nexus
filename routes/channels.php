<?php

use Illuminate\Support\Facades\Broadcast;
use App\Broadcasting\OrderChannel;
// use Illuminate\Support\Facades\Auth;
// use App\Models\User;
// use App\Models\Table;
// use App\Models\Device;
// use App\Models\DeviceOrder;
// Broadcast::channel('table.{tableId}.orders', function ($user, $tableId) {
//     // Give access if user is admin OR associated with the table
//     return $user->is_admin || $user->tables()->where('id', $tableId)->exists();
// });

// Broadcast::channel('devices.{device_id}', function ($user, int $deviceId) {
//     // Give access if user is admin OR associated with the table
//     // return $user->is_admin || 
//     return $user->is_admin ;
// });

// Broadcast::channel('devices.{device_id}', function ($device, int $deviceId) {
//     // Give access if user is admin OR associated with the table
//     // return $user->is_admin || 
//     return $device->id === $deviceId;
// });

Broadcast::channel('orders', OrderChannel::class);


