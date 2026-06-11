<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\Device;
use App\Models\DeviceOrder;
use Illuminate\Support\Carbon;

class AdminShellBadgeService
{
    public const OFFLINE_THRESHOLD_MINUTES = 5;

    /**
     * Returns live badge counts for the admin shell nav.
     *
     * @return array{orders: int, devices: int}
     */
    public function counts(): array
    {
        return [
            'orders' => $this->pendingOrderCount(),
            'devices' => $this->offlineDeviceCount(),
        ];
    }

    private function pendingOrderCount(): int
    {
        return DeviceOrder::whereIn('status', [
            OrderStatus::PENDING->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::IN_PROGRESS->value,
        ])->count();
    }

    private function offlineDeviceCount(): int
    {
        $threshold = Carbon::now()->subMinutes(self::OFFLINE_THRESHOLD_MINUTES);

        return Device::where('is_active', true)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $threshold);
            })
            ->count();
    }
}
