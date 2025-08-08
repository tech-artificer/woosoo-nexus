<?php

namespace App\Actions\Device;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Device;
use App\Models\DeviceRegistrationCode;

class RegisterDevice
{
    use AsAction;

    public function handle(array $data) : Device
    {
        $code = DeviceRegistrationCode::where('code', $data['code'])
                ->whereNull('used_at')
                ->firstOrFail();
        
        $device = Device::create([
            'name' => $data['name'],
            'table_id' => $data['table_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'last_ip_address' => $data['ip_address'] ?? null,
        ]);

        $code->update([
            'used_at' => now(),
            'used_by_device_id' => $device->id,
        ]);

        return $device;
    }
}
