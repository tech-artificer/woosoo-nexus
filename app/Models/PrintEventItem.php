<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\PrintEventItem
 * 
 * WS2: Junction table linking print events to specific order items
 * Enables item-level print tracking and prevents duplicate printing
 */
class PrintEventItem extends Model
{
    use HasFactory;

    protected $table = 'print_event_items';
    protected $fillable = [
        'print_event_id',
        'device_order_item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Relationship to the print event
     */
    public function printEvent(): BelongsTo
    {
        return $this->belongsTo(PrintEvent::class, 'print_event_id', 'id');
    }

    /**
     * Relationship to the device order item
     */
    public function deviceOrderItem(): BelongsTo
    {
        return $this->belongsTo(DeviceOrderItems::class, 'device_order_item_id', 'id');
    }
}
