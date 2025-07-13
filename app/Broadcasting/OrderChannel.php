<?php

namespace App\Broadcasting;

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\DeviceOrder;
use Illuminate\Http\Request;

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
     * Authenticate the user's access to the channel.
     */
    public function join(Request $request, DeviceOrder $deviceOrder): array|bool
    {
        return $request->user()->id === $deviceOrder->device_id || $request->user()->is_admin;
    }
}
