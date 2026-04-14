<?php

namespace App\Broadcasting;

use App\Models\DeviceOrder;

class OrderChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate channel access.
     *
     * Channel wildcard value (`$order`) is the order_id from orders.{orderId}.
     */
    public function join($user, $order): bool
    {
        if (! $user) {
            return false;
        }

        if ((bool) ($user->is_admin ?? false)) {
            return true;
        }

        $deviceId = (int) ($user->id ?? 0);
        $orderId = (int) $order;

        if ($deviceId <= 0 || $orderId <= 0) {
            return false;
        }

        return DeviceOrder::query()
            ->where('order_id', $orderId)
            ->where('device_id', $deviceId)
            ->exists();
    }
}
