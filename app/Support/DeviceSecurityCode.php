<?php

namespace App\Support;

use App\Models\Device;
use Illuminate\Support\Facades\Hash;

class DeviceSecurityCode
{
    public static function hash(string $plainSecurityCode): string
    {
        return Hash::make($plainSecurityCode);
    }

    public static function lookupHash(string $plainSecurityCode): string
    {
        $key = (string) (config('app.key') ?: 'woosoo-device-security-code');

        return hash_hmac('sha256', $plainSecurityCode, $key);
    }

    public static function isAssigned(string $plainSecurityCode, ?int $exceptDeviceId = null): bool
    {
        $lookupHash = self::lookupHash($plainSecurityCode);

        $lookupQuery = Device::query()
            ->where('security_code_lookup', $lookupHash);

        if ($exceptDeviceId !== null) {
            $lookupQuery->whereKeyNot($exceptDeviceId);
        }

        if ($lookupQuery->exists()) {
            return true;
        }

        $legacyQuery = Device::query()
            ->whereNotNull('security_code')
            ->whereNull('security_code_lookup');

        if ($exceptDeviceId !== null) {
            $legacyQuery->whereKeyNot($exceptDeviceId);
        }

        return $legacyQuery
            ->get(['id', 'security_code'])
            ->contains(fn (Device $device) => Hash::check($plainSecurityCode, (string) $device->security_code));
    }

    public static function attributesFor(string $plainSecurityCode): array
    {
        return [
            'security_code' => self::hash($plainSecurityCode),
            'security_code_lookup' => self::lookupHash($plainSecurityCode),
            'security_code_generated_at' => now(),
        ];
    }
}
