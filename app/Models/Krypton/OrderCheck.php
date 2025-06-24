<?php

namespace App\Models\Krypton;

use Illuminate\Database\Eloquent\Model;

class OrderCheck extends Model
{
    protected $connection = 'pos';
    protected $table = 'order_checks';
    protected $primaryKey = 'id';

    protected $fillable = [
        'order_id',
        'date_time_opened',
        'is_voided',
        'is_settled',
        'from_split',
        'total_amount',
        'gross_amount',
        'paid_amount',
        'change',
        'subtotal_amount',
        'tax_amount',
        'taxable_amount',
        'item_discount_amount',
        'check_discount_amount',
        'discount_amount',
        'regular_guest_count',
        'exempt_guest_count',
        'transaction_number',
        'surcharge_vatable',
        'surcharge_vat',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'date_time_opened' => 'datetime',
        'total_amount' => 'double',
        'gross_amount' => 'double',
        'paid_amount' => 'double',
        'change' => 'double',
        'subtotal_amount' => 'double',
        'tax_amount' => 'double',
        'taxable_amount' => 'double',
        'item_discount_amount' => 'double',
        'check_discount_amount' => 'double',
        'discount_amount' => 'double',
        'surcharge_vatable'  => 'double',
        'surcharge_vat'  => 'double',
    ];

    public $timestamps = false;

  

}




//   protected $fillable = [
        // 'order_id',
        // 'date_time_opened',
        // 'date_time_closed',
        // 'is_voided',
        // 'date_time_voided',
        // 'is_settled',
        // 'from_split',
        // 'total_amount',
        // 'gross_amount',
        // 'paid_amount',
        // 'change',
        // 'gratuity_amount',
        // 'surcharges_amount',
        // 'tax_sales_amount',
        // 'tax_sales_amount_discounted',
        // 'tax_exempt_sales_amount',
        // 'tax_exempt_sales_amount_discounted',
        // 'zero_rated_sales_amount',
        // 'subtotal_amount',
        // 'tax_amount',
        // 'tax_exempt_amount',
        // 'taxable_amount',
        // 'total_cost',
        // 'comp_discount',
        // 'item_discount_amount',
        // 'check_discount_amount',
        // 'discount_amount',
        // 'regular_guest_count',
        // 'exempt_guest_count',
        // 'guest_count',
        // 'cancel_order_id',
        // 'transaction_number',
        // 'or_number',
        // 'void_series',
        // 'refund_series',
        // 'created_on',
        // 'modified_on',
        // 'void_reason',
        // 'voided_get_employee_id',
        // 'surcharge_vatable',
        // 'surcharge_vat',
        // 'excess_gc_amount',
        // 'excess_gc_vatable',
        // 'excess_gc_vat',
        // 'bill_series',
        // 'old_bill_series',
        // 'resetable_transaction_number',
        // 'sc_is_taxable',
        // 'sc_is_before_tax',
        // 'sc_is_after_discount',
        // 'sc_vat_indicator'
    // ];
