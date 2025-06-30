<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;


use App\Models\Krypton\Order;
use App\Models\Krypton\OrderCheck;

class CreateOrderCheck
{
    use AsAction;

    public function handle(Order $order, array $params) : OrderCheck
    {
        return $this->createOrderCheck($order, $params);
    }

     public function createOrderCheck(Order $order, array $params){

        return OrderCheck::create([
            'order_id' => $order->id,
            'date_time_opened' => today(),
            'is_voided' => 0,
            'is_settled' => 0,
            'from_split' => 0,
            'total_amount' => $params['total_amount'],
            'paid_amount' => 0.0,
            'change' => 0.0,
            'subtotal_amount' => $params['subtotal'],
            'tax_amount' => 0.0,
            'discount_amount' => 0.0,
            'transaction_number' => $order->transaction_no,
            'gross_amount' => 0.0,
            'taxable_amount' => 0.0,
            'tax_exempt_amount' => 0.0,
            'item_discount_amount' => 0.0,
            'check_discount_amount' => 0.0,
            'regular_guest_count' => $params['guest_count'],
            'exempt_guest_count' => 0,
            'surcharges_amount' => 0.0,
            'tax_sales_amount' => 0.0,
            'tax_exempt_sales_amount' => 0.0,
            'guest_count' => $params['guest_count'],
            'comp_discount' => 0.0,
            'zero_rated_sales_amount' => 0.0,
            'tax_sales_amount_discounted' => 0.0,
            'tax_exempt_sales_amount_discounted' => 0.0,
            'surcharge_vatable' => 0.0,
            'surcharge_vat' => 0.0,
        ]);
    }

}
