<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreDeviceOrderRequest;

class DeviceOrderValidationTest extends TestCase
{
    public function test_ordered_menu_id_allows_null_and_rejects_zero(): void
    {
        $rules = (new StoreDeviceOrderRequest())->rules();

        $payload = [
            'guest_count' => 1,
            'subtotal' => 10.00,
            'tax' => 1.00,
            'discount' => 0.00,
            'total_amount' => 11.00,
            'items' => [
                [
                    'menu_id' => 1,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'price' => 10.00,
                    'note' => null,
                    'subtotal' => 10.00,
                    'tax' => 1.00,
                    'discount' => 0.00,
                    'ordered_menu_id' => null,
                ]
            ],
        ];

        $validator = Validator::make($payload, $rules);
        $this->assertTrue($validator->passes(), 'ordered_menu_id should allow null for non-meats');

        $payload['items'][0]['ordered_menu_id'] = 0;
        $validatorZero = Validator::make($payload, $rules);
        $this->assertFalse($validatorZero->passes(), 'ordered_menu_id should reject zero');
        $this->assertArrayHasKey('items.0.ordered_menu_id', $validatorZero->errors()->toArray());
    }
}
