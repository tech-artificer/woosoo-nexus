<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class OrderedMenu extends Model
{
    protected $connection = 'pos';
    protected $table = 'ordered_menus';
    protected $primaryKey = 'id';

    protected $fillable = [
        'order_id',
        'menu_id',
        'price_level_id',
        'ordered_menu_id',
        'order_check_id',
        'modifier_adjective_id',
        'cancelled_order_id',
        'seat_number',
        'quantity',
        'price',
        'original_price',
        'item_discount',
        'check_discount',
        'discount',
        'tax',
        'tax_exempt',
        'taxable_price',
        'unit_price',
        'for_kitchen_display',
        'time_sent',
        'index',
        'is_printed',
        'cost',
        'note',
        'is_held',
        'name',
        'receipt_name',
        'kitchen_name',
        'employee_log_id',
        'menu_description',
        'time_bumped',
        'tax_id',
        'is_checked',
        'kds_last_update',
        'is_taxed_removed',
        'no_tax_price',
        'refunded_qty',
        'item_gross_total',
        'item_original_wo_vat',
        'item_discount_main',
        'item_discount_adj',
        'vatable_original',
        'vatable_sales_discounted',
        'vatable_amount',
        'vatable_sub_total',
        'vat_exempt_sales',
        'vat_exempt_sales_discounted',
        'vat_exempt_amount',
        'vat_exempt_sub_total',
        'non_vatable_exempt_sales',
        'non_vatable_sub_total',
        'zero_rated_value',
        'zero_rated_sub_total',
        'sub_total',
        'is_cancelled',
        'reason',
        'is_removed_queueing',
        'guest_count'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'menu_id' => 'integer',
        'price_level_id' => 'integer',
        'ordered_menu_id' => 'integer',
        'order_check_id' => 'integer',
        'quantity' => 'integer',
        'created_on' => 'datetime',
        'modified_on' => 'datetime'
    ];


    public $timestamps = false;

    public function order() : BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

     protected static function boot() : void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_on = now();
            $model->modified_on = now();
        });

        static::updating(function ($model) {
            $model->modified_on = now();
        });
    }   
}
