<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Krypton\Menu;
use App\Enums\ItemStatus;

class DeviceOrderItems extends Model
{
    use SoftDeletes, HasFactory;
    protected $table = 'device_order_items';
    protected $fillable = [
        'order_id',
        'ordered_menu_id',
        'menu_id',
        'quantity',
        'price',
        'subtotal',
        'tax',
        'discount',
        'total',
        'notes',
        'seat_number',
        'index',
        'is_refill',
        'is_printed',
        'printed_at',
        'printed_by_print_event_id',
        'print_type',
        'status',
        'client_submission_id',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'tax' => 'decimal:4',
        'discount' => 'decimal:4',
        'total' => 'decimal:4',
        'status' => ItemStatus::class,
        'is_refill' => 'boolean',
        'is_printed' => 'boolean',
        'printed_at' => 'datetime',
    ];

    public function scopeRefills(Builder $query): Builder
    {
        return $query->where('is_refill', true);
    }

    public function device_order()
    {
        return $this->belongsTo(DeviceOrder::class, 'order_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /**
     * WS2: Relationship to the print event that printed this item
     */
    public function printedByPrintEvent(): BelongsTo
    {
        return $this->belongsTo(PrintEvent::class, 'printed_by_print_event_id', 'id');
    }

    /**
     * WS2: Relationship to print event items that include this item
     */
    public function printEventItems()
    {
        return $this->hasMany(PrintEventItem::class, 'device_order_item_id', 'id');
    }
}
