<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Device;

class MessageAcknowledgement extends Model
{
    protected $fillable = [
        'message_id',
        'device_id',
        'acknowledged_at',
        'client_info'
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime'
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
