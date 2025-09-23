<?php

namespace App\Actions\Order;

use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\DB;
use App\Models\Krypton\OrderCheck;

class CreateOrderCheck
{
    use AsAction;

    public function handle(array $attr)
    {
        return $this->createOrderCheck($attr);
    }

     public function createOrderCheck(array $attr = []){
     
        // Convert all = value into $variable = $params['snake_case']
        $orderId = $attr['order_id'];
        $dateTimeOpened = now();
        $isVoided = false;
        $isSettled = false;
        $fromSplit = false;
        $totalAmount = $attr['total'] ?? 0.00;
        $paidAmount = 0.00;
        $change = 0.00;
        $subtotalAmount = $attr['subtotal'] ?? 0.00;
        $taxAmount = $attr['tax'] ?? 0.00;
        $taxExemptAmount = $attr['tax_exempt_amount'] ?? 0.00;
        $discountAmount = $attr['discount_amount'] ?? 0.00;
        $grossAmount = $attr['total'] ?? 0.00;
        $taxableAmount = $attr['taxable'] ?? 0.00;
        $itemDiscountAmount = $attr['item_discount_amount'] ?? 0.00;
        $checkDiscountAmount = $attr['check_discount_amount'] ?? 0.00;
        $regularGuestCount = $attr['regular_guest_count'] ?? 0;
        $exemptGuestCount = $attr['exempt_guest_count'] ?? 0;
        $surchargeAmount = $attr['surcharge_amount'] ?? 0.00;
        $taxSalesAmount = $attr['tax_sales_amount'] ?? 0.00;
        $taxExemptSalesAmount = $attr['tax_exempt_sales_amount'] ?? 0.00;
        $guestCount = $attr['guest_count'] ?? 1;
        $compDiscount = $attr['comp_discount'] ?? 0.00;
        $zeroRatedSalesAmount = $attr['zero_rated_sales_amount'] ?? 0.00;
        $taxSalesAmountDiscounted = $attr['tax_sales_amount_discounted'] ?? 0.00;
        $taxExemptSalesAmountDiscounted = $attr['tax_exempt_sales_amount_discounted'] ?? 0.00;
        $surchargeVatable = $attr['surcharge_vatable'] ?? 0.00;
        $surchargeVat = $attr['surcharge_vat'] ?? 0.00;

        $params = [
            $orderId, $dateTimeOpened, $isVoided, $isSettled, $fromSplit,
            $totalAmount, $paidAmount, $change, $subtotalAmount, $taxAmount,
            $discountAmount, $grossAmount, $taxableAmount, $taxExemptAmount, 
            $itemDiscountAmount, $checkDiscountAmount, $regularGuestCount,
            $exemptGuestCount, $surchargeAmount, $taxSalesAmount,
            $taxExemptSalesAmount, $guestCount, $compDiscount,
            $zeroRatedSalesAmount, $taxSalesAmountDiscounted,
            $taxExemptSalesAmountDiscounted, $surchargeVatable, $surchargeVat
        ];

        $placeholdersArray = array_fill(0, count($params), '?');
        $placeholders = implode(', ', $placeholdersArray);

        // Assuming your procedure is named something like 'create_and_fetch_order_check'
        $orderCheck = OrderCheck::fromQuery('CALL create_order_check(' . $placeholders . ')', $params)->first();

        if (empty($orderCheck)) {
            throw new \Exception("Failed to create order check.");
        }

        return $orderCheck;
    }

}
