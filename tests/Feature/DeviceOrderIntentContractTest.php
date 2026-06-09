<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\DeviceOrderApiController;
use App\Http\Requests\RefillOrderRequest;
use App\Http\Requests\StoreDeviceOrderRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Verifies that both request validators accept the exact intent-only
 * payload shapes the tablet staging branch sends, and reject invalid inputs.
 *
 * Most checks are pure validator coverage. The expandIntentPayload check seeds
 * the package row required by the real controller contract.
 */
class DeviceOrderIntentContractTest extends TestCase
{
    use RefreshDatabase;

    // ─── Initial order ────────────────────────────────────────────────────────

    public function test_accepts_intent_only_initial_order_payload(): void
    {
        $rules = (new StoreDeviceOrderRequest)->rules();

        $payload = [
            'guest_count' => 3,
            'package_id' => 46,
            'items' => [
                ['menu_id' => 10, 'quantity' => 2],
                ['menu_id' => 13, 'quantity' => 2],
            ],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertTrue(
            $validator->passes(),
            'Intent-only payload should pass validation. Errors: '.json_encode($validator->errors()->toArray())
        );
    }

    public function test_initial_order_rejects_missing_package_id(): void
    {
        $rules = (new StoreDeviceOrderRequest)->rules();

        $payload = [
            'guest_count' => 3,
            'items' => [['menu_id' => 10, 'quantity' => 2]],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('package_id', $validator->errors()->toArray());
    }

    public function test_initial_order_rejects_missing_items(): void
    {
        $rules = (new StoreDeviceOrderRequest)->rules();

        $payload = [
            'guest_count' => 3,
            'package_id' => 46,
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('items', $validator->errors()->toArray());
    }

    public function test_initial_order_rejects_item_without_menu_id(): void
    {
        $rules = (new StoreDeviceOrderRequest)->rules();

        $payload = [
            'guest_count' => 3,
            'package_id' => 46,
            'items' => [['quantity' => 2]],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('items.0.menu_id', $validator->errors()->toArray());
    }

    public function test_initial_order_does_not_require_client_totals(): void
    {
        $rules = (new StoreDeviceOrderRequest)->rules();

        $payload = [
            'guest_count' => 2,
            'package_id' => 47,
            'items' => [['menu_id' => 15, 'quantity' => 1]],
            // subtotal / tax / discount / total_amount intentionally omitted
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertTrue(
            $validator->passes(),
            'Client totals must be optional. Errors: '.json_encode($validator->errors()->toArray())
        );
    }

    public function test_initial_order_does_not_require_item_name_or_price(): void
    {
        $rules = (new StoreDeviceOrderRequest)->rules();

        $payload = [
            'guest_count' => 2,
            'package_id' => 47,
            'items' => [
                ['menu_id' => 15, 'quantity' => 1],
                // no 'name', no 'price', no 'subtotal'
            ],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertTrue(
            $validator->passes(),
            'items.*.name and items.*.price must be optional. Errors: '.json_encode($validator->errors()->toArray())
        );
    }

    public function test_initial_order_request_strips_non_intent_fields_before_validation(): void
    {
        $request = StoreDeviceOrderRequest::create('/api/devices/create-order', 'POST', [
            'guest_count' => 2,
            'package_id' => 47,
            'total_amount' => 123.45,
            'items' => [
                [
                    'menu_id' => 15,
                    'quantity' => 1,
                    'price' => 99.99,
                    'name' => 'Ignored',
                ],
            ],
        ]);

        $request->setContainer($this->app)->validateResolved();

        $this->assertSame(
            [
                'guest_count' => 2,
                'package_id' => 47,
                'items' => [
                    ['menu_id' => 15, 'quantity' => 1],
                ],
            ],
            $request->validated()
        );
    }

    // ─── Refill order ─────────────────────────────────────────────────────────

    public function test_accepts_intent_only_refill_payload(): void
    {
        $rules = (new RefillOrderRequest)->rules();

        $payload = [
            'items' => [
                ['menu_id' => 10, 'quantity' => 1],
                ['menu_id' => 13, 'quantity' => 1],
            ],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertTrue(
            $validator->passes(),
            'Intent-only refill payload should pass validation. Errors: '.json_encode($validator->errors()->toArray())
        );
    }

    public function test_refill_rejects_missing_menu_id(): void
    {
        $rules = (new RefillOrderRequest)->rules();

        $payload = [
            'items' => [
                ['quantity' => 1],
            ],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('items.0.menu_id', $validator->errors()->toArray());
    }

    public function test_refill_does_not_require_item_name(): void
    {
        $rules = (new RefillOrderRequest)->rules();

        $payload = [
            'items' => [
                ['menu_id' => 10, 'quantity' => 2],
                // no 'name'
            ],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertTrue(
            $validator->passes(),
            'items.*.name must be optional for refill. Errors: '.json_encode($validator->errors()->toArray())
        );
    }

    public function test_refill_rejects_zero_quantity(): void
    {
        $rules = (new RefillOrderRequest)->rules();

        $payload = [
            'items' => [['menu_id' => 10, 'quantity' => 0]],
        ];

        $validator = Validator::make($payload, $rules);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('items.0.quantity', $validator->errors()->toArray());
    }

    public function test_refill_does_not_accept_client_price(): void
    {
        $rules = (new RefillOrderRequest)->rules();

        $payload = [
            'items' => [
                ['menu_id' => 10, 'quantity' => 1, 'price' => 999.99],
            ],
        ];

        $validator = Validator::make($payload, $rules);
        $validator->passes(); // run validation so validated() is available
        $validated = $validator->validated();

        $this->assertArrayNotHasKey(
            'price',
            $validated['items'][0] ?? [],
            'Refill request must not accept client-supplied price (backend owns pricing).'
        );
    }

    public function test_expand_intent_payload_shape(): void
    {
        DB::table('packages')->insert([
            'id' => 46,
            'name' => 'Classic Feast',
            'krypton_menu_id' => 46,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = new DeviceOrderApiController;
        $expand = new \ReflectionMethod($controller, 'expandIntentPayload');
        $expand->setAccessible(true);

        $input = [
            'guest_count' => 3,
            'package_id' => 46,
            'items' => [
                ['menu_id' => 10, 'quantity' => 2],
                ['menu_id' => 13, 'quantity' => 1],
            ],
        ];

        $result = $expand->invoke($controller, $input);

        $this->assertCount(1, $result['items']);
        $this->assertEquals(46, $result['items'][0]['menu_id']);
        $this->assertEquals(3, $result['items'][0]['quantity']);
        $this->assertTrue($result['items'][0]['is_package']);
        $this->assertCount(2, $result['items'][0]['modifiers']);
        $this->assertEquals(10, $result['items'][0]['modifiers'][0]['menu_id']);
        $this->assertEquals(13, $result['items'][0]['modifiers'][1]['menu_id']);
        $this->assertGreaterThan(0, $result['items'][0]['menu_id']);
    }

    public function test_expand_intent_payload_rejects_invalid_initial_payload(): void
    {
        $controller = new DeviceOrderApiController;
        $expand = new \ReflectionMethod($controller, 'expandIntentPayload');
        $expand->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('package_id is required and must be greater than 0');

        $expand->invoke($controller, [
            'guest_count' => 3,
            'items' => [
                ['menu_id' => 10, 'quantity' => 2],
            ],
        ]);
    }
}
