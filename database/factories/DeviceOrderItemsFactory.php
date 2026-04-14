<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\DeviceOrderItems;
use App\Enums\ItemStatus;

class DeviceOrderItemsFactory extends Factory
{
    protected $model = DeviceOrderItems::class;

    public function definition()
    {
        $price = $this->faker->randomFloat(2, 50, 500);
        $quantity = $this->faker->numberBetween(1, 5);
        $subtotal = $price * $quantity;
        $tax = $subtotal * 0.10;
        $total = $subtotal + $tax;

        return [
            'order_id' => null, // Should be set via relationship
            'ordered_menu_id' => null,
            'menu_id' => $this->faker->numberBetween(1, 100),
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => $subtotal,
            'tax' => $tax,
            // discount removed - column may not exist in test DB
            'total' => $total,
            'notes' => $this->faker->optional()->sentence(),
            'seat_number' => 1,
            'index' => 1,
            // status removed - may not exist in all test DB iterations
            'is_refill' => false,
        ];
    }

    /**
     * Indicate that the item is a refill.
     */
    public function refill()
    {
        return $this->state(fn (array $attributes) => [
            'is_refill' => true,
            'notes' => 'Refill',
        ]);
    }

    /**
     * Set the item for a specific menu.
     */
    public function forMenu(int $menuId)
    {
        return $this->state(fn (array $attributes) => [
            'menu_id' => $menuId,
        ]);
    }
}
