<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Device;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingNotification extends Model
{
      protected $fillable = [
        'device_id',
        'message_id',
        'channel',
        'event',
        'payload',
        'priority',
        'delivered',
        'expires_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered' => 'boolean',
        'expires_at' => 'datetime'
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function scopeUndelivered($query)
    {
        return $query->where('delivered', false)
                    ->where('expires_at', '>', now());
    }

    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }
}
