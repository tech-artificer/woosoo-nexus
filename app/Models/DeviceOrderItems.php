<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Krypton\Menu;
use App\Enums\ItemStatus;

class DeviceOrderItems extends Model
{
    use SoftDeletes;
    protected $table = 'device_order_items';
    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'tax' => 'decimal:4',
        'discount' => 'decimal:4',
        'total' => 'decimal:4',
        'status' => ItemStatus::class,
    ];

    public function device_order()
    {
        return $this->belongsTo(DeviceOrder::class, 'order_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }
}
