<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceRegistrationCode extends Model
{
    protected $table = "device_registration_codes";

    protected $fillable = [
        'code',
        'used_at',
        'used_by_device_id',
    ];

    public function device() : BelongsTo {
        return $this->belongsTo(Device::class);
    }
}
