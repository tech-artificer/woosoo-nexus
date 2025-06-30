<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Table extends Model
{
    protected $connection = 'pos';
    protected $table = 'tables';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $hidden = [
        'created_on', 
        'modified_on',
        'order_created_in',
        'position_x',
        'position_y',
        'z_index',
        'rotation',
        'merchant_id',
        'employee_name',

    ];

    public function tableOrders() : HasMany
    {
        return $this->hasMany(TableOrder::class);
    }

    // public function orders() : HasMany
    // {
    //     return $this->hasMany(TableOrder::class);
    // }

    public function device() : HasOne {
        return $this->hasOne(Device::class, 'device_id');
    }

    public function group() : BelongsTo {
        return $this->belongsTo(TableGroup::class, 'table_group_id');
    }

     public function type() : BelongsTo {
        return $this->belongsTo(TableType::class, 'table_type_id');
    }
  
}
