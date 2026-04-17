<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    /**
     * Determine whether the user can view any devices.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('devices.view');
    }

    /**
     * Determine whether the user can view a single device.
     */
    public function view(User $user, Device $device): bool
    {
        return $user->hasPermissionTo('devices.view');
    }

    /**
     * Determine whether the user can create devices.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('devices.register');
    }

    /**
     * Determine whether the user can update a device.
     * Covers status toggling and security-code regeneration.
     */
    public function update(User $user, Device $device): bool
    {
        return $user->hasPermissionTo('devices.update');
    }

    /**
     * Determine whether the user can delete a device.
     */
    public function delete(User $user, Device $device): bool
    {
        return $user->hasPermissionTo('devices.delete');
    }

    /**
     * Determine whether the user can restore a soft-deleted device.
     */
    public function restore(User $user, Device $device): bool
    {
        return $user->hasPermissionTo('devices.restore');
    }

    /**
     * Determine whether the user can permanently delete a device.
     */
    public function forceDelete(User $user, Device $device): bool
    {
        return $user->hasPermissionTo('devices.delete');
    }
}
