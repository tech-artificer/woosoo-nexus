<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderUpdateLog extends Model
{
    protected $table = 'order_update_logs';

    protected $primaryKey = 'id';

    public function deviceOrder(): HasOne
    {
        return $this->hasOne(DeviceOrder::class, 'order_id', 'order_id');
    }

    // public function created(OrderUpdateLog $orderUpdateLog): void
    // {

    //     // A new row was just created.
    //     // You can now perform actions, like logging to an update_log table.
    //     // For example:
    //     // UpdateLog::create([
    //     //     'model_id' => $yourModel->id,
    //     //     'action' => 'created',
    //     //     'data' => json_encode($yourModel->getOriginal())
    //     // ]);
    // }

}
