<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Enums\OrderStatus;
class OrderUpdateLog extends Model
{
    protected $table = 'order_update_logs';
    protected $primaryKey = 'id';

    protected $dispatchesEvents = [
        // 'created' => \App\Events\OrderUpdateLogCreated::class,
        // 'updated' => \App\Events\OrderUpdateLogUpdated::class
    ];

    public function deviceOrder() : HasOne
    {
        return $this->hasOne(DeviceOrder::class, 'order_id', 'order_id');
    }

    // protected static function booted() {
        
    //     // Detect when a row is created
    //     static::created(function ($model) {
    //         // Logic after the row is created
    //         \Log::info('A new row was created: ' . json_encode($model->toArray()));
    //     });
    // }

}
