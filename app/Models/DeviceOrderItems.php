<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Krypton\Menu;

class DeviceOrderItems extends Model
{
    use SoftDeletes;
    protected $table = 'device_order_items';
    protected $guarded = [];

    public function device_order()
    {
        return $this->belongsTo(DeviceOrder::class, 'order_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }
}
