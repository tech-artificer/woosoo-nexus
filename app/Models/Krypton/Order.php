<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;
   
    protected $connection = 'pos';
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'is_open' => 'boolean',
        'is_transferred' => 'boolean',
        'is_voided' => 'boolean',
        'is_available' => 'boolean',
        'is_online_order' => 'boolean',
    ];

    protected $dates = [
        'date_time_opened',
        'date_time_closed',
    ];


    public function revenue() : BelongsTo
    {
        return $this->belongsTo(Revenue::class, 'revenue_id');
    }

    public function terminal() : BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }


    public function tableOrders() : HasMany
    {
        return $this->hasMany(TableOrder::class, 'order_id');
    }

     public function tableLinks() : HasMany
    {
        return $this->hasMany(TableLink::class, 'order_id');
    }

    public function orderCheck() : HasOne
    {
        return $this->hasOne(OrderCheck::class, 'order_id');
    }

    public function orderedMenus() : HasMany
    {
        return $this->hasMany(OrderedMenu::class, 'order_id');
    }

    // public function createOrderCashTray($params) {

    //     $placeholdersArray = array_fill(0, count($params), '?');
    //     $placeholders = implode(', ', $placeholdersArray);
    //     // return $placeholders;
    //     $orderCashTray = Order::fromQuery('CALL wt_create_order(' . $placeholders . ')', $params);

    //     return $orderCashTray;
    // }

    // public function createTableOrder($params) {

    //     $placeholdersArray = array_fill(0, count($params), '?');
    //     $placeholders = implode(', ', $placeholdersArray);

    //     Order::fromQuery('CALL create_table_order(' . $placeholders . ')', $params);


    // }


}
