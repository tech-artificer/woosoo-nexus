<?php

namespace Tests\Feature;

use App\Http\Requests\StoreDeviceOrderRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DeviceOrderValidationTest extends TestCase
{
    public function test_validated_output_strips_client_pricing_and_modifier_fields(): void
    {
        $request = StoreDeviceOrderRequest::create('/api/devices/create-order', 'POST', [
            'guest_count'  => 2,
            'package_id'   => 46,
            'subtotal'     => 999.99,
            'tax'          => 99.99,
            'discount'     => 50.00,
            'total_amount' => 1049.98,
            'session_id'   => 123,
            'items'        => [
                [
                    'menu_id'         => 10,
                    'quantity'        => 2,
                    'name'            => 'Client Name',
                    'price'           => 100.00,
                    'subtotal'        => 200.00,
                    'ordered_menu_id' => 999,
                    'modifiers'       => [
                        ['menu_id' => 1, 'quantity' => 1],
                    ],
                ],
            ],
        ]);

        $request->setContainer($this->app)->validateResolved();

        $validated = $request->validated();

        $this->assertSame(
            [
                'guest_count' => 2,
                'package_id'  => 46,
                'items'       => [
                    ['menu_id' => 10, 'quantity' => 2],
                ],
            ],
            $validated
        );
    }

    public function test_extra_top_level_fields_do_not_pass_validation_rules(): void
    {
        $rules = (new StoreDeviceOrderRequest())->rules();

        $payload = [
            'guest_count'  => 1,
            'package_id'   => 46,
            'client_total' => 500.00,
            'items'        => [
                ['menu_id' => 1, 'quantity' => 1],
            ],
        ];

        $request = StoreDeviceOrderRequest::create('/api/devices/create-order', 'POST', $payload);
        $request->setContainer($this->app)->validateResolved();

        $this->assertArrayNotHasKey('client_total', $request->validated());

        $validator = Validator::make($request->validated(), $rules);
        $this->assertTrue($validator->passes());
    }
}
