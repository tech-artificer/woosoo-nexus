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
        'event_type',
        'meta',
        'is_acknowledged',
        'acknowledged_at',
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
}
