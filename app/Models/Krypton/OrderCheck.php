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
        'paid_amount',
        'change',
        'subtotal_amount',
        'tax_amount',
        'discount_amount',
        'transaction_number',
        'gross_amount',
        'taxable_amount',
        'tax_exempt_amount',
        'item_discount_amount',
        'check_discount_amount',
        'regular_guest_count',
        'exempt_guest_count',
        'surcharges_amount',
        'tax_sales_amount',
        'tax_exempt_sales_amount',
        'guest_count',
        'comp_discount',
        'zero_rated_sales_amount',
        'tax_sales_amount_discounted',
        'tax_exempt_sales_amount_discounted',
        'surcharge_vatable',
        'surcharge_vat'
    ];

    protected $casts = [
        'order_id' => 'integer',
        // 'date_time_opened' => 'datetime',
        'total_amount' => 'decimal:2',
        // 'gross_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'item_discount_amount' => 'decimal:2',
        'check_discount_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'surcharge_vatable'  => 'decimal:2',
        'surcharge_vat'  => 'decimal:2',
    ];

    public $timestamps = false;

    public function createOrderCheck() {

        $details = $this->toArray(); 

        $numberOfParameters = count($details);
        // Create an array of '?' strings, one for each parameter.
        $placeholdersArray = array_fill(0, $numberOfParameters, '?');
        // Join them with a comma and space to form the placeholder string.
        $placeholders = implode(', ', $placeholdersArray);
        // 2. Extract Values
        // array_values() extracts all the values from the associative array
        // and returns them as a new numerically indexed array.
        $params = array_values($details);

        // Now, call your fromQuery method with the generated placeholders and parameters
        return Order::fromQuery('CALL create_order_check(' . $placeholders . ')', $params);
    }

}