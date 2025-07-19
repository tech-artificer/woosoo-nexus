<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableOrder extends Model
{
    protected $connection = 'pos';
    protected $table = 'table_orders';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'table_id',
    ];

    public function order() : BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function table() : BelongsTo
    {
        return $this->belongsTo(Table::class, 'table_id');
    }
    


}
