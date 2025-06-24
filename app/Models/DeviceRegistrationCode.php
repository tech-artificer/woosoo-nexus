<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceRegistrationCode extends Model
{
    protected $table = "device_registration_codes";

    protected $fillable = [
        'code',
        'used_at',
        'used_by_device_id',
    ];
}
