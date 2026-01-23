<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\PrintEvent
 *
 * attempts: server-side counter incremented on each ack/fail report.
 */
class PrintEvent extends Model
{
    use HasFactory;

    protected $table = 'print_events';
    protected $fillable = [
        'device_order_id',
        'printer_id',
        'printer_name',                // NEW: Human-readable printer name
        'event_type',
        'meta',
        'is_acknowledged',
        'acknowledged_at',
        'acknowledged_by_device_id',   // NEW: Track which relay device acked (audit trail)
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_acknowledged' => 'boolean',
        'acknowledged_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function deviceOrder(): BelongsTo
    {
        return $this->belongsTo(DeviceOrder::class, 'device_order_id', 'id');
    }

    /**
     * B2: Audit trail relationship - which relay device acknowledged this print event.
     * Links to the Device that sent the ack, creating an immutable audit trail.
     * 
     * @return BelongsTo
     */
    public function acknowledgedByDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'acknowledged_by_device_id', 'id');
    }
}
