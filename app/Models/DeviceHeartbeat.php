<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceHeartbeat extends Model
{
    public $timestamps = false;

    protected $table = 'device_heartbeats';

    protected $fillable = [
        'device_id', 'recorded_at', 'battery_level',
        'memory_used_bytes', 'memory_total_bytes',
        'storage_used_bytes', 'storage_total_bytes',
        'wifi_signal_dbm', 'ping_ms', 'app_version', 'metadata',
    ];

    protected $casts = [
        'recorded_at'        => 'datetime',
        'battery_level'      => 'decimal:2',
        'metadata'           => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Derived: storage usage percentage (0–100) or null if data unavailable.
     */
    public function getStoragePercentAttribute(): ?float
    {
        if (! $this->storage_used_bytes || ! $this->storage_total_bytes) {
            return null;
        }

        return round(($this->storage_used_bytes / $this->storage_total_bytes) * 100, 1);
    }
}
