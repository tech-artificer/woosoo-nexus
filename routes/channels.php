<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('Device.{id}', function ($device, $id) {
    return (int) $device->id === (int) $id;
});
