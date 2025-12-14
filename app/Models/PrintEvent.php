<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintEvent extends Model
{
    protected $table = 'print_events';
    protected $guarded = [];

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
