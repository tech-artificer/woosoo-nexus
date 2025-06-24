<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableOrder extends Model
{
    protected $connection = 'pos';
    protected $table = 'table_orders';

    protected $timestamps = false;

    protected $fillable = [
        'order_id',
        'table_id',
        'parent_table_id',
        'is_cleared',
        'is_printed',
    ];

    protected $casts = [
        'is_cleared' => 'boolean',
        'is_printed' => 'boolean',
        'order_id' => 'integer',  
        'table_id' => 'integer',  
    ];

    public function table() : BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

}
