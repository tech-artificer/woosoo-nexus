<?php

namespace App\Policies;

use App\Models\DeviceOrder;
use App\Models\User;

class DeviceOrderPolicy
{
    public function view(User $user, DeviceOrder $order): bool
    {
        return (bool) ($user->is_admin ?? false);
    }
}
