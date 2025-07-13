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
    public function join(User $user, $deviceId): array|bool
    {
        // $user = $request->user();

        \Log::info('Join check', [
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'is_admin' => $user->is_admin,
        ]);

        return true;// (int) $user->id === (int) $deviceId || $user->is_admin;
    }
}
