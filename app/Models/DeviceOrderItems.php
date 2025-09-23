<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class DeviceOrderItems extends Model
{
    use SoftDeletes;
    protected $table = 'device_order_items';
    protected $guarded = [];

    public function device_order()
    {
        return $this->belongsTo(DeviceOrder::class);
    }
}
